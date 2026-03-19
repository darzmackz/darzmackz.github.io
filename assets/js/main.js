(function () {
  var html = document.documentElement;

  function updateThemeToggle(btn) {
    if (!btn) return;
    var theme = html.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    var next = theme === 'dark' ? 'light' : 'dark';
    btn.setAttribute('aria-label', 'Switch to ' + next + ' mode');
    btn.setAttribute('title', 'Switch to ' + next + ' mode');
  }

  var themeBtn = document.querySelector('.dark-mode-toggle');
  if (themeBtn) {
    updateThemeToggle(themeBtn);
    themeBtn.addEventListener('click', function () {
      var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', next);
      try { localStorage.setItem('theme', next); } catch (e) {}
      updateThemeToggle(themeBtn);
    });
  }

  var toggle = document.querySelector('.nav-toggle');
  var nav = document.querySelector('.site-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var expanded = this.getAttribute('aria-expanded') === 'true';
      this.setAttribute('aria-expanded', String(!expanded));
      nav.classList.toggle('open');
    });

    nav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        nav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  var scrollBtn = document.querySelector('.scroll-to-top');
  var progressBar = document.querySelector('.reading-progress');
  if (!progressBar) {
    progressBar = document.createElement('div');
    progressBar.className = 'reading-progress';
    progressBar.setAttribute('aria-hidden', 'true');
    document.body.prepend(progressBar);
  }

  var ticking = false;
  function updateScrollUI() {
    ticking = false;
    var total = document.documentElement.scrollHeight - window.innerHeight;
    var progress = total > 0 ? Math.min(100, (window.scrollY / total) * 100) : 0;
    progressBar.style.width = progress + '%';

    if (scrollBtn) {
      if (window.scrollY > 240) {
        scrollBtn.classList.add('visible');
      } else {
        scrollBtn.classList.remove('visible');
      }
    }
  }

  function requestScrollUpdate() {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(updateScrollUI);
  }

  window.addEventListener('scroll', requestScrollUpdate, { passive: true });
  window.addEventListener('resize', requestScrollUpdate, { passive: true });
  updateScrollUI();

  if (scrollBtn) {
    scrollBtn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  var adsClient = document.body.getAttribute('data-adsense-client');
  if (adsClient) {
    var adsLoaded = false;

    function loadAdsense() {
      if (adsLoaded) return;
      adsLoaded = true;
      var script = document.createElement('script');
      script.async = true;
      script.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' + encodeURIComponent(adsClient);
      script.crossOrigin = 'anonymous';
      document.head.appendChild(script);
    }

    function scheduleAdsense() {
      if ('requestIdleCallback' in window) {
        window.requestIdleCallback(loadAdsense, { timeout: 4000 });
      } else {
        window.setTimeout(loadAdsense, 2500);
      }
    }

    window.addEventListener('load', function () {
      window.setTimeout(scheduleAdsense, 1200);
    }, { once: true });

    ['scroll', 'pointerdown', 'keydown', 'touchstart'].forEach(function (eventName) {
      window.addEventListener(eventName, loadAdsense, { once: true, passive: true });
    });
  }
})();
