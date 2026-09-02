<?php /** Expects $__activeAccountTab in scope: overview|addresses|orders|wishlist */ ?>
<nav class="account-nav">
  <a href="/account.php" class="<?= $__activeAccountTab === 'overview' ? 'active' : '' ?>">Account overview</a>
  <a href="/addresses.php" class="<?= $__activeAccountTab === 'addresses' ? 'active' : '' ?>">Addresses</a>
  <a href="/orders.php" class="<?= $__activeAccountTab === 'orders' ? 'active' : '' ?>">Order history</a>
  <a href="/wishlist.php" class="<?= $__activeAccountTab === 'wishlist' ? 'active' : '' ?>">Wishlist</a>
  <a href="/logout.php">Log out</a>
</nav>
