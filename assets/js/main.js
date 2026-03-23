(function () {
  var html = document.documentElement;

  function bindImageFallbacks() {
    document.querySelectorAll('[data-image-error="hide"]').forEach(function (img) {
      img.addEventListener('error', function () {
        img.classList.add('is-hidden');
      }, { once: true });
    });

    document.querySelectorAll('[data-image-error="avatar-fallback"]').forEach(function (img) {
      img.addEventListener('error', function () {
        var fallbackText = img.getAttribute('data-fallback-text') || '';
        var container = img.parentElement;
        if (!container) return;
        container.textContent = fallbackText;
      }, { once: true });
    });
  }

  function initBlogArchive() {
    var archive = document.querySelector('[data-blog-archive]');
    if (!archive) return;

    var list = archive.querySelector('[data-blog-list]');
    var items = Array.prototype.slice.call(archive.querySelectorAll('[data-post-item]'));
    var searchInput = archive.querySelector('[data-blog-filter="search"]');
    var yearSelect = archive.querySelector('[data-blog-filter="year"]');
    var categorySelect = archive.querySelector('[data-blog-filter="category"]');
    var resetBtn = archive.querySelector('[data-blog-filter="reset"]');
    var results = archive.querySelector('[data-blog-results]');
    var emptyState = archive.querySelector('[data-blog-empty]');

    if (!list || !items.length || !searchInput || !yearSelect || !categorySelect || !results || !emptyState) return;

    function getParams() {
      return new URLSearchParams(window.location.search);
    }

    function applyParamsToInputs() {
      var params = getParams();
      searchInput.value = params.get('q') || '';
      yearSelect.value = params.get('year') || '';
      categorySelect.value = params.get('category') || '';
    }

    function updateUrl(search, year, category) {
      var params = getParams();
      if (search) params.set('q', search); else params.delete('q');
      if (year) params.set('year', year); else params.delete('year');
      if (category) params.set('category', category); else params.delete('category');
      var query = params.toString();
      var nextUrl = window.location.pathname + (query ? '?' + query : '') + window.location.hash;
      window.history.replaceState({}, '', nextUrl);
    }

    function pluralize(count) {
      return count === 1 ? 'post' : 'posts';
    }

    function filterPosts() {
      var search = searchInput.value.trim().toLowerCase();
      var year = yearSelect.value;
      var category = categorySelect.value;
      var visible = 0;

      items.forEach(function (item) {
        var haystack = (item.getAttribute('data-post-search') || '').toLowerCase();
        var itemYear = item.getAttribute('data-post-year') || '';
        var itemCategories = (item.getAttribute('data-post-categories') || '').split('|').filter(Boolean);
        var matches = (!search || haystack.indexOf(search) !== -1) &&
          (!year || itemYear === year) &&
          (!category || itemCategories.indexOf(category) !== -1);

        item.classList.toggle('is-hidden', !matches);
        item.toggleAttribute('hidden', !matches);
        if (matches) visible += 1;
      });

      results.textContent = visible + ' ' + pluralize(visible);
      emptyState.classList.toggle('is-hidden', visible !== 0);
      updateUrl(search, year, category);
    }

    applyParamsToInputs();
    filterPosts();

    searchInput.addEventListener('input', filterPosts);
    yearSelect.addEventListener('change', filterPosts);
    categorySelect.addEventListener('change', filterPosts);
    resetBtn.addEventListener('click', function () {
      searchInput.value = '';
      yearSelect.value = '';
      categorySelect.value = '';
      filterPosts();
    });
  }

  function queueAdsenseSlots() {
    window.adsbygoogle = window.adsbygoogle || [];
    document.querySelectorAll('.adsbygoogle[data-ads-slot-state="pending"]').forEach(function (slot) {
      slot.setAttribute('data-ads-slot-state', 'queued');
      try {
        window.adsbygoogle.push({});
      } catch (e) {
        slot.setAttribute('data-ads-slot-state', 'pending');
      }
    });
  }

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
  var scrollVisible = false;
  function updateScrollUI() {
    ticking = false;
    var total = document.documentElement.scrollHeight - window.innerHeight;
    var progress = total > 0 ? Math.min(100, (window.scrollY / total) * 100) : 0;
    progressBar.style.transform = 'scaleX(' + (progress / 100) + ')';

    if (scrollBtn) {
      var shouldShow = window.scrollY > 240;
      if (shouldShow === scrollVisible) return;
      scrollVisible = shouldShow;
      if (shouldShow) {
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

  bindImageFallbacks();
  initBlogArchive();

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
      script.addEventListener('load', queueAdsenseSlots, { once: true });
      document.head.appendChild(script);
    }

    function scheduleAdsense() {
      if ('requestIdleCallback' in window) {
        window.requestIdleCallback(loadAdsense, { timeout: 4000 });
      } else {
        window.setTimeout(loadAdsense, 2500);
      }
    }

    queueAdsenseSlots();

    window.addEventListener('load', function () {
      queueAdsenseSlots();
      window.setTimeout(scheduleAdsense, 1200);
    }, { once: true });

    ['scroll', 'pointerdown', 'keydown', 'touchstart'].forEach(function (eventName) {
      window.addEventListener(eventName, loadAdsense, { once: true, passive: true });
    });
  }
})();
