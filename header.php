

<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>


  <!-- ===== TOP INFO BAR ===== -->
  <div class="topbar">
    <div class="topbar__inner">
      <div class="topbar__left">
        <span><i class="fa-solid fa-phone"></i>Morbi 24/7 Helpline: <strong>+91 91378 91378</strong></span>
        <span><i class="fa-solid fa-headset"></i>Morbi Civil Live Desk: <strong>1800-MORBI-HELP</strong></span>
        <span><i class="fa-solid fa-shield-halved"></i>Regd Public Trust: E/1428/Morbi</span>
      </div>
      <div class="topbar__right">
        <span class="live-dot">Live Emergency Desk</span>
      </div>
    </div>
  </div>

  <!-- ===== HEADER / NAVIGATION ===== -->
  <header class="header" id="header">
    <div class="header__inner">

      <a href="index.html" class="brand">
        <img src="assets/logo.png" alt="Young India Group logo" class="brand__logo">
        <span class="brand__text">
          <strong>Young India Group – The Deven Rabari Foundation</strong>
          <small lang="gu">દેવેન રબારી ફાઉન્ડેશન (મોરબી) &nbsp; SPEAK • ACT • CHANGE</small>
        </span>
      </a>

      <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>

      
<nav class="nav" id="nav">
  <a href="index.php"
     class="nav__link <?= $currentPage === 'index.php' ? 'is-active' : '' ?>">
    Home
  </a>

  <a href="about.php"
     class="nav__link <?= $currentPage === 'about.php' ? 'is-active' : '' ?>">
    About Us
  </a>

  <a href="bloodhub.php"
     class="nav__link <?= $currentPage === 'bloodhub.php' ? 'is-active' : '' ?>">
    Blood Hub
  </a>

  <a href="activities.php"
     class="nav__link <?= $currentPage === 'activities.php' ? 'is-active' : '' ?>">
    Activities
  </a>

  <a href="volunteers.php"
     class="nav__link <?= $currentPage === 'volunteers.php' ? 'is-active' : '' ?>">
    Volunteers
  </a>

  <a href="index.php#contact" class="nav__link">
    Contact
  </a>

  <a href="bloodhub.php" class="btn btn--sos">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <span lang="gu">SOS<br>રક્ત સહાય</span>
  </a>

  <a href="bloodhub.php" class="btn btn--donate">
    <i class="fa-solid fa-hand-holding-heart"></i>
    Donate Now
  </a>
</nav>

    </div>
  </header>