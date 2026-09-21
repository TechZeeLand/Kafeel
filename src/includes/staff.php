<?php
/**
 * Staff management helpers. A "staff member" is an admin account (table `admins`) plus profile
 * details. Only owners can create, edit, disable, delete or reset the password of staff; every
 * staff member can see their OWN details read-only on Admin > My account.
 *
 * Privacy notes:
 *  - The optional document (e.g. an NID scan) is stored in the database, never in the public
 *    uploads folder, and is only served by admin/staff_document.php to owners and to that person.
 *  - The NID number is shown in full only on the person's own page and the owner edit form;
 *    lists and the activity log show it masked.
 */

const STAFF_BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const STAFF_GENDERS = ['Male', 'Female', 'Other'];
const STAFF_DOC_MAX_BYTES = 5 * 1024 * 1024;
const STAFF_DOC_TYPES = ['application/pdf' => 'PDF', 'image/jpeg' => 'JPEG image', 'image/png' => 'PNG image', 'image/webp' => 'WebP image'];
const STAFF_COLUMNS = 'id, username, name, role, status, must_change_password, phone, email, address, blood_group, gender, nid_number, created_at, updated_at';

function staff_get(int $id): ?array {
    $s = db()->prepare('SELECT ' . STAFF_COLUMNS . ' FROM admins WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/** All staff, newest first, with a flag for "has a document". Filters are all optional. */
function staff_list(string $q = ''): array {
    $sql = 'SELECT ' . STAFF_COLUMNS . ', (SELECT COUNT(*) FROM admin_documents d WHERE d.admin_id = admins.id) AS has_doc FROM admins';
    $params = [];
    if ($q !== '') {
        $like = '%' . like_escape($q) . '%';
        $sql .= " WHERE name LIKE ? ESCAPE '|' OR username LIKE ? ESCAPE '|' OR phone LIKE ? ESCAPE '|' OR email LIKE ? ESCAPE '|'";
        $params = [$like, $like, $like, $like];
    }
    $stmt = db()->prepare($sql . ' ORDER BY (role = \'owner\') DESC, name ASC');
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Owners who can still sign in, optionally not counting one account (used to protect the last owner). */
function staff_active_owner_count(?int $excludeId = null): int {
    $s = db()->prepare("SELECT COUNT(*) FROM admins WHERE role = 'owner' AND status = 'active' AND id <> ?");
    $s->execute([$excludeId ?? 0]);
    return (int) $s->fetchColumn();
}

/** "1234567890123" → "•••••••••0123" (list pages and the activity log never show the full number). */
function staff_mask_nid(?string $nid): string {
    $nid = (string) $nid;
    if ($nid === '') return '—';
    return str_repeat('•', max(0, strlen($nid) - 4)) . substr($nid, -4);
}

function staff_password_error(string $pw): ?string {
    if (strlen($pw) < 10) return 'The password must be at least 10 characters.';
    if ($pw === 'ChangeMe123!') return 'Please choose a different password from the default one.';
    return null;
}

/**
 * Validates the staff form. Every profile field is required (the document is the only optional part).
 * @param array<string,mixed> $in     raw POST
 * @param array|null          $existing the row being edited, or null when adding
 * @return array{0: string[], 1: array<string,?string>} [errors, cleaned values]
 */
function staff_validate(array $in, ?array $existing): array {
    $errors = [];
    $v = fn (string $k) => trim((string) ($in[$k] ?? ''));
    $id = $existing['id'] ?? 0;

    $c = [
        'username' => strtolower($v('username')),
        'name' => preg_replace('/\s+/u', ' ', $v('name')),
        'role' => ($in['role'] ?? '') === 'owner' ? 'owner' : 'staff',
        'phone' => $v('phone'),
        'email' => strtolower($v('email')),
        'address' => trim(str_replace("\r", '', (string) ($in['address'] ?? ''))),
        'blood_group' => $v('blood_group'),
        'gender' => $v('gender'),
        'nid_number' => preg_replace('/[\s-]/', '', $v('nid_number')),
    ];

    if (!preg_match('/^[a-z0-9._-]{3,40}$/', $c['username'])) $errors[] = 'Username: 3–40 characters, using only letters, numbers, dot, dash or underscore.';
    else {
        $d = db()->prepare('SELECT id FROM admins WHERE username = ? AND id <> ?'); $d->execute([$c['username'], $id]);
        if ($d->fetch()) $errors[] = 'The username "' . $c['username'] . '" is already taken.';
    }
    if (mb_strlen($c['name']) < 2 || mb_strlen($c['name']) > 120) $errors[] = 'Enter the full name (2–120 characters).';
    if (!preg_match('/^\+?[\d\s().-]{5,40}$/', $c['phone']) || strlen(preg_replace('/\D/', '', $c['phone'])) < 7) $errors[] = 'Enter a valid phone number.';
    if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL) || strlen($c['email']) > 160) $errors[] = 'Enter a valid email address.';
    else {
        $d = db()->prepare('SELECT id FROM admins WHERE email = ? AND id <> ?'); $d->execute([$c['email'], $id]);
        if ($d->fetch()) $errors[] = 'Another staff account already uses that email address.';
    }
    if (mb_strlen($c['address']) < 5 || mb_strlen($c['address']) > 500) $errors[] = 'Enter the address (5–500 characters).';
    if (!in_array($c['blood_group'], STAFF_BLOOD_GROUPS, true)) $errors[] = 'Choose a blood group.';
    if (!in_array($c['gender'], STAFF_GENDERS, true)) $errors[] = 'Choose a gender.';
    if (!preg_match('/^\d+$/', $c['nid_number']) || !in_array(strlen($c['nid_number']), [10, 13, 17], true)) $errors[] = 'The NID number must be 10, 13 or 17 digits.';
    else {
        $d = db()->prepare('SELECT id FROM admins WHERE nid_number = ? AND id <> ?'); $d->execute([$c['nid_number'], $id]);
        if ($d->fetch()) $errors[] = 'That NID number is already registered to another staff account.';
    }
    return [$errors, $c];
}

/**
 * Checks an uploaded document BEFORE anything is saved, so a bad file can't leave a half-saved record.
 * The type is decided from the file's real contents, never from its name or the browser's claim.
 * @return array{0: ?array{data:string,mime:string,size:int,name:string}, 1: ?string} [payload or null, error or null]
 *         [null, null] means "no file was chosen".
 */
function staff_check_document(?array $file): array {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null];
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) return [null, 'The document is too large — the limit is 5 MB.'];
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) return [null, 'The document could not be uploaded. Please try again.'];
    if ($file['size'] > STAFF_DOC_MAX_BYTES) return [null, 'The document is too large — the limit is 5 MB.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(STAFF_DOC_TYPES[$mime])) return [null, 'Only PDF, JPG, PNG or WebP documents are allowed.'];
    $data = file_get_contents($file['tmp_name']);
    if ($data === false || $data === '') return [null, 'The document is empty.'];
    if ($mime === 'application/pdf' && !str_starts_with($data, '%PDF-')) return [null, 'That does not look like a valid PDF.'];
    if ($mime !== 'application/pdf' && @getimagesizefromstring($data) === false) return [null, 'That does not look like a valid image.'];
    $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
    $name = preg_replace('/[^\p{L}\p{N} ._()-]+/u', '', basename((string) $file['name']));
    $name = trim(mb_substr($name, 0, 150));
    if ($name === '' || !preg_match('/\.' . $ext . '$/i', $name)) $name = ($name !== '' ? pathinfo($name, PATHINFO_FILENAME) : 'document') . '.' . $ext;
    return [['data' => $data, 'mime' => $mime, 'size' => strlen($data), 'name' => $name], null];
}

/** Saves (or replaces) a person's document from a payload returned by staff_check_document(). */
function staff_save_document(int $adminId, array $doc, ?int $byAdminId): void {
    $s = db()->prepare('INSERT INTO admin_documents (admin_id, original_name, mime, size, data, uploaded_by) VALUES (?,?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE original_name = VALUES(original_name), mime = VALUES(mime), size = VALUES(size), data = VALUES(data), uploaded_by = VALUES(uploaded_by), uploaded_at = CURRENT_TIMESTAMP');
    $s->bindValue(1, $adminId, PDO::PARAM_INT); $s->bindValue(2, $doc['name']); $s->bindValue(3, $doc['mime']); $s->bindValue(4, $doc['size'], PDO::PARAM_INT);
    $s->bindValue(5, $doc['data'], PDO::PARAM_LOB); $s->bindValue(6, $byAdminId, $byAdminId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $s->execute();
}

function staff_document_meta(int $adminId): ?array {
    $s = db()->prepare('SELECT id, original_name, mime, size, uploaded_at FROM admin_documents WHERE admin_id = ?');
    $s->execute([$adminId]);
    return $s->fetch() ?: null;
}

function staff_delete_document(int $adminId): void {
    db()->prepare('DELETE FROM admin_documents WHERE admin_id = ?')->execute([$adminId]);
}

function staff_human_size(int $bytes): string {
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
}
