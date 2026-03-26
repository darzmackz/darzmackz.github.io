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

  function initPostEngagement() {
    var root = document.querySelector('[data-post-engagement]');
    if (!root) return;

    var postUrl = root.getAttribute('data-post-url') || window.location.href;
    var postPath = root.getAttribute('data-post-path') || window.location.pathname;
    var postTitle = root.getAttribute('data-post-title') || document.title;
    var engagementApi = (root.getAttribute('data-engagement-api') || '').trim();
    var postDate = root.getAttribute('data-post-date') || '';
    var postDescription = root.getAttribute('data-post-description') || '';
    var postCategories = (root.getAttribute('data-post-categories') || '').split('|').filter(Boolean);
    var postTags = (root.getAttribute('data-post-tags') || '').split('|').filter(Boolean);
    var viewCountEl = root.querySelector('[data-view-count]');
    var shareFeedback = root.querySelector('[data-share-feedback]');
    var reactionFeedback = root.querySelector('[data-reaction-feedback]');
    var commentsList = root.querySelector('[data-comments-list]');
    var commentsEmpty = root.querySelector('[data-comments-empty]');
    var commentForm = root.querySelector('[data-comment-form]');
    var commentFeedback = root.querySelector('[data-comment-feedback]');
    var reactionStorageKey = 'postReaction:' + postPath;
    var visitorTokenKey = 'engagementVisitorToken';
    var engagementUnavailable = false;
    var commenterNameKey = 'commenterName';
    var commenterEmailKey = 'commenterEmail';

    function setFeedback(el, message, isError) {
      if (!el) return;
      el.textContent = message || '';
      el.classList.toggle('is-error', Boolean(isError));
    }

    function createVisitorToken() {
      return 'visitor-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
    }

    function getVisitorToken() {
      var token = '';
      try {
        token = window.localStorage.getItem(visitorTokenKey) || '';
        if (!token) {
          token = createVisitorToken();
          window.localStorage.setItem(visitorTokenKey, token);
        }
      } catch (e) {
        token = createVisitorToken();
      }
      return token;
    }

    function apiUrl(action) {
      var separator = engagementApi.indexOf('?') === -1 ? '?' : '&';
      return engagementApi + separator + 'action=' + encodeURIComponent(action);
    }

    function requestEngagement(action, options) {
      var requestOptions = options || {};
      if (!engagementApi) {
        engagementUnavailable = true;
        return Promise.reject(new Error('Engagement API not configured'));
      }
      if (engagementUnavailable) {
        return Promise.reject(new Error('Engagement API unavailable'));
      }

      var method = requestOptions.method || 'GET';
      var fetchOptions = {
        method: method,
        headers: {
          'Accept': 'application/json'
        }
      };

      if (method !== 'GET') {
        fetchOptions.headers['Content-Type'] = 'application/json';
        fetchOptions.body = JSON.stringify(requestOptions.body || {});
      }

      var url = apiUrl(action);
      if (method === 'GET' && requestOptions.query) {
        Object.keys(requestOptions.query).forEach(function (key) {
          url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(requestOptions.query[key]);
        });
      }

      return window.fetch(url, fetchOptions).then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (data) {
          if (!response.ok || data.ok === false) {
            throw new Error(data.error || data.message || 'Engagement request failed');
          }
          return data;
        });
      }).catch(function (error) {
        if (error && error.name === 'TypeError') {
          engagementUnavailable = true;
        }
        throw error;
      });
    }

    function formatCount(value) {
      var count = Number(value || 0);
      return count.toLocaleString();
    }

    function escapeHtml(value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function formatCommentDate(value) {
      if (!value) return '';
      var parsed = new Date(value);
      if (isNaN(parsed.getTime())) return value;
      return parsed.toLocaleString();
    }

    function renderComments(items) {
      if (!commentsList || !commentsEmpty) return;
      if (!items.length) {
        commentsList.innerHTML = '';
        commentsEmpty.hidden = false;
        commentsEmpty.textContent = 'No comments yet. Start the conversation.';
        return;
      }

      commentsEmpty.hidden = true;
      commentsList.innerHTML = items.map(function (comment) {
        var author = escapeHtml(comment.author_name || 'Anonymous');
        var message = escapeHtml(comment.comment_body || '').replace(/\n/g, '<br>');
        return '<article class="post-comment-item">' +
          '<header class="post-comment-head">' +
          '<strong>' + author + '</strong>' +
          '<time datetime="' + escapeHtml(comment.created_at || '') + '">' + escapeHtml(formatCommentDate(comment.created_at || '')) + '</time>' +
          '</header>' +
          '<p class="post-comment-body">' + message + '</p>' +
          '</article>';
      }).join('');
    }

    function loadComments() {
      if (!commentsList || !commentsEmpty) return;
      if (!engagementApi) {
        commentsEmpty.hidden = false;
        commentsEmpty.textContent = 'Set engagement_api_base in _config.yml to enable comments.';
        return;
      }

      commentsEmpty.hidden = false;
      commentsEmpty.textContent = 'Loading comments...';
      requestEngagement('get-comments', { method: 'GET', query: { path: postPath } }).then(function (data) {
        renderComments(data.comments || []);
      }).catch(function () {
        commentsEmpty.hidden = false;
        commentsEmpty.textContent = 'Comments are temporarily unavailable.';
      });
    }

    function syncMetadata() {
      if (!engagementApi) return;
      requestEngagement('sync-post', {
        method: 'POST',
        body: {
          path: postPath,
          title: postTitle,
          url: postUrl,
          description: postDescription,
          published_at: postDate,
          categories: postCategories,
          tags: postTags
        }
      }).catch(function () {});
    }

    function bindComments() {
      if (!commentForm) return;
      var nameInput = commentForm.querySelector('[name="name"]');
      var emailInput = commentForm.querySelector('[name="email"]');
      var messageInput = commentForm.querySelector('[name="message"]');
      var honeypotInput = commentForm.querySelector('[name="company"]');

      try {
        if (nameInput) nameInput.value = window.localStorage.getItem(commenterNameKey) || '';
        if (emailInput) emailInput.value = window.localStorage.getItem(commenterEmailKey) || '';
      } catch (e) {}

      var submitBtn = commentForm.querySelector('[type="submit"]');

      commentForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (typeof commentForm.reportValidity === 'function' && !commentForm.reportValidity()) {
          return;
        }
        if (!engagementApi) {
          setFeedback(commentFeedback, 'Comments are not configured yet.', true);
          return;
        }

        var payload = {
          path: postPath,
          title: postTitle,
          url: postUrl,
          author_name: nameInput ? nameInput.value.trim() : '',
          author_email: emailInput ? emailInput.value.trim() : '',
          comment_body: messageInput ? messageInput.value.trim() : '',
          company: honeypotInput ? honeypotInput.value.trim() : '',
          visitor_token: getVisitorToken()
        };

        if (submitBtn) submitBtn.disabled = true;

        requestEngagement('comment', { method: 'POST', body: payload }).then(function (data) {
          try {
            if (nameInput) window.localStorage.setItem(commenterNameKey, nameInput.value.trim());
            if (emailInput) window.localStorage.setItem(commenterEmailKey, emailInput.value.trim());
          } catch (e) {}
          if (messageInput) messageInput.value = '';
          setFeedback(commentFeedback, data && data.message ? data.message : 'Comment posted successfully.', false);
          renderComments(data.comments || []);
        }).catch(function (error) {
          setFeedback(commentFeedback, error && error.message ? error.message : 'Your comment could not be submitted right now.', true);
        }).finally(function () {
          if (submitBtn) submitBtn.disabled = false;
        });
      });
    }

    function updateViewCount() {
      if (!viewCountEl) return;
      viewCountEl.textContent = '...';

      var sessionKey = 'viewed:' + postPath;
      var viewedThisSession = false;
      try {
        viewedThisSession = window.sessionStorage.getItem(sessionKey) === '1';
      } catch (e) {}

      var request = viewedThisSession ?
        requestEngagement('get', { method: 'GET', query: { path: postPath } }) :
        requestEngagement('view', {
          method: 'POST',
          body: {
            path: postPath,
            title: postTitle,
            url: postUrl,
            visitor_token: getVisitorToken()
          }
        });

      request.then(function (data) {
        viewCountEl.textContent = formatCount(data.views);
        if (!viewedThisSession) {
          try { window.sessionStorage.setItem(sessionKey, '1'); } catch (e) {}
        }
      }).catch(function () {
        viewCountEl.textContent = engagementApi ? 'Offline' : 'Setup needed';
      });
    }

    function setReactionButtonsDisabled(message) {
      root.querySelectorAll('[data-reaction]').forEach(function (button) {
        button.disabled = true;
        button.classList.add('is-disabled');
      });
      setFeedback(reactionFeedback, message, true);
    }

    function updateReactionButtons() {
      if (!engagementApi) {
        setReactionButtonsDisabled('Set engagement_api_base in _config.yml to enable reactions.');
        return;
      }

      if (engagementUnavailable) {
        setReactionButtonsDisabled('Reactions are temporarily unavailable because the engagement API cannot be reached.');
        return;
      }

      var savedReaction = '';
      try {
        savedReaction = window.localStorage.getItem(reactionStorageKey) || '';
      } catch (e) {}

      requestEngagement('get', { method: 'GET', query: { path: postPath } }).then(function (data) {
        var reactions = data.reactions || {};
        root.querySelectorAll('[data-reaction]').forEach(function (button) {
          var reaction = button.getAttribute('data-reaction');
          button.classList.toggle('is-selected', reaction === savedReaction);
          button.disabled = false;
          button.classList.remove('is-disabled');
          var countEl = button.querySelector('[data-reaction-count]');
          if (countEl) {
            countEl.textContent = formatCount(reactions[reaction]);
          }
        });
      }).catch(function () {
        setReactionButtonsDisabled('Reactions are temporarily unavailable because the engagement API cannot be reached.');
      });
    }

    function bindReactions() {
      root.querySelectorAll('[data-reaction]').forEach(function (button) {
        button.addEventListener('click', function () {
          var reaction = button.getAttribute('data-reaction');
          var emoji = button.getAttribute('data-reaction-emoji') || '';
          var savedReaction = '';

          try {
            savedReaction = window.localStorage.getItem(reactionStorageKey) || '';
          } catch (e) {}

          if (savedReaction === reaction) {
            setFeedback(reactionFeedback, 'You already reacted with ' + emoji + '.', false);
            return;
          }

          requestEngagement('react', {
            method: 'POST',
            body: {
              path: postPath,
              title: postTitle,
              url: postUrl,
              reaction: reaction,
              visitor_token: getVisitorToken()
            }
          }).then(function (data) {
            try { window.localStorage.setItem(reactionStorageKey, reaction); } catch (e) {}
            var countEl = button.querySelector('[data-reaction-count]');
            if (countEl) {
              countEl.textContent = formatCount(data.reactions && data.reactions[reaction]);
            }
            updateReactionButtons();
            setFeedback(reactionFeedback, 'Reaction saved: ' + emoji, false);
          }).catch(function () {
            setReactionButtonsDisabled('Reactions are temporarily unavailable because the engagement API cannot be reached.');
          });
        });
      });
    }

    function bindShareActions() {
      var nativeBtn = root.querySelector('[data-share-native]');
      var copyBtn = root.querySelector('[data-share-copy]');

      if (nativeBtn) {
        if (!navigator.share) {
          nativeBtn.hidden = true;
        } else {
          nativeBtn.addEventListener('click', function () {
            navigator.share({
              title: postTitle,
              text: postTitle,
              url: postUrl
            }).then(function () {
              setFeedback(shareFeedback, 'Thanks for sharing this post.', false);
            }).catch(function (error) {
              if (error && error.name === 'AbortError') return;
              setFeedback(shareFeedback, 'Share dialog could not be opened.', true);
            });
          });
        }
      }

      if (copyBtn) {
        copyBtn.addEventListener('click', function () {
          if (!navigator.clipboard || !navigator.clipboard.writeText) {
            setFeedback(shareFeedback, 'Copy is not available in this browser.', true);
            return;
          }

          navigator.clipboard.writeText(postUrl).then(function () {
            setFeedback(shareFeedback, 'Post link copied to clipboard.', false);
          }).catch(function () {
            setFeedback(shareFeedback, 'The link could not be copied.', true);
          });
        });
      }
    }

    syncMetadata();
    updateViewCount();
    bindShareActions();
    bindReactions();
    updateReactionButtons();
    loadComments();
    bindComments();
  }

  function initContactForm() {
    var form = document.querySelector('[data-contact-form]');
    if (!form) return;

    var apiBase = (document.body.getAttribute('data-site-api') || '').trim();
    var feedback = document.querySelector('[data-contact-feedback]');

    function setContactFeedback(message, isError) {
      if (!feedback) return;
      feedback.textContent = message || '';
      feedback.classList.toggle('is-error', Boolean(isError));
    }

    function requestContact(action, body) {
      if (!apiBase) {
        return Promise.reject(new Error('API not configured'));
      }

      var separator = apiBase.indexOf('?') === -1 ? '?' : '&';
      return window.fetch(apiBase + separator + 'action=' + encodeURIComponent(action), {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(body || {})
      }).then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (data) {
          if (!response.ok || data.ok === false) {
            throw new Error(data.error || data.message || 'Contact request failed');
          }
          return data;
        });
      });
    }

    var contactSubmit = form.querySelector('[type="submit"]');

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
        return;
      }
      var formData = new FormData(form);
      if (contactSubmit) contactSubmit.disabled = true;

      requestContact('inquiry', {
        name: String(formData.get('name') || '').trim(),
        email: String(formData.get('email') || '').trim(),
        subject: String(formData.get('subject') || '').trim(),
        message: String(formData.get('message') || '').trim(),
        company: String(formData.get('company') || '').trim(),
        page_url: window.location.href
      }).then(function (data) {
        form.reset();
        setContactFeedback(data && data.message ? data.message : 'Your message has been sent.', false);
      }).catch(function (error) {
        setContactFeedback(apiBase ? (error && error.message ? error.message : 'Your message could not be sent right now.') : 'Set engagement_api_base in _config.yml to enable inquiries.', true);
      }).finally(function () {
        if (contactSubmit) contactSubmit.disabled = false;
      });
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
      document.dispatchEvent(new CustomEvent('site-theme-change', { detail: { theme: next } }));
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
  initPostEngagement();
  initContactForm();

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

