// Mobile navigation toggle
(function () {
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.querySelector('.site-nav');

  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var expanded = this.getAttribute('aria-expanded') === 'true';
      this.setAttribute('aria-expanded', String(!expanded));
      nav.classList.toggle('open');
    });

    // Close nav when a link is clicked
    nav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        nav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }
})();

// Dark mode toggle
(function () {
  var STORAGE_KEY = 'theme';
  var html = document.documentElement;

  function applyTheme(theme) {
    html.setAttribute('data-theme', theme);
    try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) {}
  }

  // Load saved preference, otherwise respect system preference
  var saved;
  try { saved = localStorage.getItem(STORAGE_KEY); } catch (e) {}

  if (saved === 'dark' || saved === 'light') {
    applyTheme(saved);
  } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    applyTheme('dark');
  }

  var btn = document.querySelector('.dark-mode-toggle');
  if (btn) {
    btn.addEventListener('click', function () {
      var current = html.getAttribute('data-theme');
      applyTheme(current === 'dark' ? 'light' : 'dark');
    });
  }
})();

// Scroll-to-top button
(function () {
  var btn = document.querySelector('.scroll-to-top');
  if (!btn) return;

  function onScroll() {
    if (window.scrollY > 300) {
      btn.classList.add('visible');
    } else {
      btn.classList.remove('visible');
    }
  }

  window.addEventListener('scroll', onScroll, { passive: true });

  btn.addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
})();

// Reading progress bar (shown only on post pages)
(function () {
  var bar = document.createElement('div');
  bar.className = 'reading-progress';
  document.body.prepend(bar);

  var content = document.querySelector('.post-content') || document.querySelector('.page-content') || document.querySelector('.site-main');
  if (!content) return;

  function updateProgress() {
    var total = document.documentElement.scrollHeight - window.innerHeight;
    var progress = total > 0 ? Math.min(100, (window.scrollY / total) * 100) : 0;
    bar.style.width = progress + '%';
  }

  window.addEventListener('scroll', updateProgress, { passive: true });
  updateProgress();
})();
