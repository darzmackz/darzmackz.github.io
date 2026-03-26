(function () {
  if (typeof window === 'undefined' || typeof sections === 'undefined') return;

  var KEY_ADMIN_API_KEY = 'admin_api_key_local';
  var KEY_ADMIN_NAME = 'admin_display_name_local';
  var state = {
    items: [],
    meta: { page: 1, pages: 1, total: 0, page_size: 20 },
    search: '',
    status: 'all',
    selectedId: 0,
    selected: null,
    loading: false
  };

  if (sections.indexOf('inquiries') === -1) {
    sections.splice(sections.length - 1, 0, 'inquiries');
  }

  function qs(id) {
    return document.getElementById(id);
  }

  function val(id) {
    var el = qs(id);
    return el ? String(el.value || '').trim() : '';
  }

  function setVal(id, value) {
    var el = qs(id);
    if (el) el.value = value || '';
  }

  function renderAlert(type, message) {
    return '<div class="alert alert-' + type + '">' + esc(message) + '</div>';
  }

  function statusPill(status) {
    return '<span class="pill inquiry-status inquiry-status-' + esc(status) + '">' + esc(status.charAt(0).toUpperCase() + status.slice(1)) + '</span>';
  }

  function formatDate(value) {
    if (!value) return 'Not set';
    var d = new Date(value);
    return isNaN(d.getTime()) ? esc(value) : d.toLocaleString();
  }

  function localAdminKey() {
    return load(KEY_ADMIN_API_KEY) || '';
  }

  function localAdminName() {
    return load(KEY_ADMIN_NAME) || 'Admin';
  }

  function prefillInquirySettings() {
    setVal('st-admin-api-key', localAdminKey());
    setVal('st-admin-name', localAdminName());
  }

  function saveInquirySettings() {
    var apiKey = val('st-admin-api-key');
    var adminName = val('st-admin-name') || 'Admin';
    if (!apiKey) {
      showAlert('settings-alert', 'error', 'Admin API key is required for inquiry management.');
      return;
    }
    store(KEY_ADMIN_API_KEY, apiKey);
    store(KEY_ADMIN_NAME, adminName);
    showAlert('settings-alert', 'success', 'Inquiry management credentials saved in this browser.');
  }

  function inquiryApiBase() {
    return (typeof engagementApiBase === 'function' ? engagementApiBase() : '').trim();
  }

  function requestInquiry(action, options) {
    var apiBase = inquiryApiBase();
    var adminKey = localAdminKey();
    if (!apiBase) return Promise.reject(new Error('Engagement API not configured.'));
    if (!adminKey) return Promise.reject(new Error('Admin API key is not configured in Settings.'));

    var opts = options || {};
    var method = opts.method || 'GET';
    var separator = apiBase.indexOf('?') === -1 ? '?' : '&';
    var url = apiBase + separator + 'action=' + encodeURIComponent(action);
    if (method === 'GET' && opts.query) {
      Object.keys(opts.query).forEach(function (key) {
        if (typeof opts.query[key] === 'undefined' || opts.query[key] === null || opts.query[key] === '') return;
        url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(opts.query[key]);
      });
    }

    var fetchOptions = {
      method: method,
      headers: {
        'Accept': 'application/json',
        'X-Admin-Key': adminKey
      }
    };

    if (method !== 'GET') {
      fetchOptions.headers['Content-Type'] = 'application/json';
      fetchOptions.body = JSON.stringify(opts.body || {});
    }

    return fetch(url, fetchOptions).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        if (!response.ok || data.ok === false) {
          throw new Error(data.error || data.message || 'Inquiry request failed.');
        }
        return data;
      });
    });
  }

  function renderInquiryList() {
    var wrap = qs('inquiries-list');
    var summary = qs('inquiries-summary');
    if (!wrap || !summary) return;

    summary.textContent = state.meta.total ? ('Showing ' + state.items.length + ' of ' + state.meta.total + ' inquiries') : 'No inquiries yet';

    if (state.loading) {
      wrap.innerHTML = '<div class="loading"><span class="spin"></span> Loading inquiries...</div>';
      return;
    }

    if (!state.items.length) {
      wrap.innerHTML = '<div class="card card-soft"><p class="muted">No inquiries match the current filters.</p></div>';
      return;
    }

    wrap.innerHTML = state.items.map(function (item) {
      var active = item.id === state.selectedId ? ' is-active' : '';
      return '<button class="inquiry-list-item' + active + '" type="button" data-action="open-inquiry" data-id="' + item.id + '">' +
        '<span class="inquiry-list-top">' + statusPill(item.status) + '<span class="muted">' + esc(item.sender_email_mask || '') + '</span></span>' +
        '<strong>' + esc(item.subject || 'No subject') + '</strong>' +
        '<span class="muted">' + esc(item.sender_name || 'Unknown sender') + '</span>' +
        '<span class="inquiry-preview">' + esc(item.preview || '') + '</span>' +
        '<span class="inquiry-list-date">' + esc(formatDate(item.created_at)) + '</span>' +
      '</button>';
    }).join('');

    var pager = qs('inquiries-pagination');
    if (pager) {
      var buttons = '';
      for (var page = 1; page <= state.meta.pages; page += 1) {
        buttons += '<button class="btn sm g' + (page === state.meta.page ? ' pager-current' : '') + '" type="button" data-action="inquiry-page" data-page="' + page + '">' + page + '</button>';
      }
      pager.innerHTML = buttons;
    }
  }

  function renderInquiryTimeline(items, kind) {
    if (!items || !items.length) {
      return '<p class="muted">No ' + kind + ' yet.</p>';
    }
    return items.map(function (item) {
      var extra = kind === 'comments'
        ? '<span class="pill inquiry-visibility inquiry-visibility-' + esc(item.visibility) + '">' + esc(item.visibility) + '</span>'
        : '<span class="pill inquiry-delivery inquiry-delivery-' + esc(item.delivery_status || 'unknown') + '">' + esc(item.delivery_status || 'unknown') + '</span>';
      var body = kind === 'comments' ? item.comment_body : item.reply_body;
      var subtitle = kind === 'comments'
        ? esc(item.admin_name || 'Admin')
        : esc((item.admin_name || 'Admin') + ' to ' + (item.sent_to_email_mask || 'recipient'));
      var heading = kind === 'comments' ? 'Comment' : (item.reply_subject || 'Reply');
      return '<article class="inquiry-timeline-item">' +
        '<div class="inquiry-timeline-head"><strong>' + esc(heading) + '</strong>' + extra + '</div>' +
        '<div class="muted">' + subtitle + ' • ' + esc(formatDate(item.created_at)) + '</div>' +
        '<p>' + esc(body || '').replace(/\n/g, '<br>') + '</p>' +
      '</article>';
    }).join('');
  }

  function renderInquiryDetail() {
    var wrap = qs('inquiry-detail');
    if (!wrap) return;
    if (!state.selected) {
      wrap.innerHTML = '<div class="card card-soft"><h3>Select an inquiry</h3><p class="muted">Choose a submission from the list to review the message, update its status, reply, or add internal notes.</p></div>';
      return;
    }

    var inquiry = state.selected;
    wrap.innerHTML = '<div class="card inquiry-detail-card">' +
      '<div class="inquiry-detail-head"><div><h3>' + esc(inquiry.subject || 'No subject') + '</h3><p class="muted">Received ' + esc(formatDate(inquiry.created_at)) + '</p></div><div class="inquiry-head-pills">' + statusPill(inquiry.status) + '</div></div>' +
      '<div class="inquiry-meta-grid">' +
        '<div><span class="muted">From</span><strong>' + esc(inquiry.sender_name || 'Unknown sender') + '</strong></div>' +
        '<div><span class="muted">Email</span><strong>' + esc(inquiry.sender_email || inquiry.sender_email_mask || 'Unavailable') + '</strong></div>' +
        '<div><span class="muted">Page</span><strong>' + esc(inquiry.page_url || 'Direct inquiry') + '</strong></div>' +
        '<div><span class="muted">Updated</span><strong>' + esc(formatDate(inquiry.updated_at)) + '</strong></div>' +
      '</div>' +
      '<div class="inquiry-message"><h4>Original Message</h4><p>' + esc(inquiry.message_body || '').replace(/\n/g, '<br>') + '</p></div>' +
      '<div class="inquiry-actions-bar">' +
        '<label class="fg"><span>Status</span><select id="inquiry-status-select"><option value="new">New</option><option value="read">Read</option><option value="replied">Replied</option><option value="closed">Closed</option><option value="spam">Spam</option></select></label>' +
        '<button class="btn has-icon" type="button" data-action="save-inquiry-status" data-id="' + inquiry.id + '">Save Status</button>' +
        '<button class="btn d has-icon" type="button" data-action="delete-inquiry" data-id="' + inquiry.id + '">Delete Inquiry</button>' +
      '</div>' +
      '<div class="grid g2 inquiry-detail-panels">' +
        '<div class="card card-soft"><h4>Reply</h4><div class="fg"><label for="inquiry-reply-subject">Subject</label><input id="inquiry-reply-subject" value="' + esc(inquiry.subject || '') + '"></div><div class="fg"><label for="inquiry-reply-message">Reply message</label><textarea id="inquiry-reply-message" rows="8"></textarea></div><div class="fg"><label for="inquiry-reply-note">Optional note</label><textarea id="inquiry-reply-note" rows="4" placeholder="Internal context or a public follow-up comment."></textarea></div><div class="fg"><label for="inquiry-reply-visibility">Optional note visibility</label><select id="inquiry-reply-visibility"><option value="internal">Internal</option><option value="public">Public</option></select></div><button class="btn p" type="button" data-action="reply-inquiry" data-id="' + inquiry.id + '">Send Reply</button></div>' +
        '<div class="card card-soft"><h4>Add Comment</h4><div class="fg"><label for="inquiry-comment-body">Comment</label><textarea id="inquiry-comment-body" rows="6" placeholder="Add an internal note or a public comment."></textarea></div><div class="fg"><label for="inquiry-comment-visibility">Visibility</label><select id="inquiry-comment-visibility"><option value="internal">Internal</option><option value="public">Public</option></select></div><button class="btn" type="button" data-action="comment-inquiry" data-id="' + inquiry.id + '">Add Comment</button></div>' +
      '</div>' +
      '<div id="inquiry-detail-alert"></div>' +
      '<div class="grid g2 inquiry-history-grid">' +
        '<div class="card card-soft"><h4>Comments</h4>' + renderInquiryTimeline(inquiry.comments, 'comments') + '</div>' +
        '<div class="card card-soft"><h4>Replies</h4>' + renderInquiryTimeline(inquiry.replies, 'replies') + '</div>' +
      '</div>' +
    '</div>';

    setVal('inquiry-status-select', inquiry.status || 'new');
  }

  function loadInquiries(resetPage) {
    if (resetPage) state.meta.page = 1;
    state.loading = true;
    renderInquiryList();
    requestInquiry('admin-list-inquiries', {
      method: 'GET',
      query: {
        page: state.meta.page,
        page_size: state.meta.page_size,
        status: state.status,
        search: state.search
      }
    }).then(function (data) {
      state.loading = false;
      state.items = data.items || [];
      state.meta = data.meta || state.meta;
      renderInquiryList();
      if (state.selectedId && state.items.some(function (item) { return item.id === state.selectedId; })) {
        loadInquiryDetail(state.selectedId, true);
      } else if (!state.selectedId && state.items.length) {
        loadInquiryDetail(state.items[0].id, true);
      } else if (!state.items.length) {
        state.selectedId = 0;
        state.selected = null;
        renderInquiryDetail();
      }
    }).catch(function (error) {
      state.loading = false;
      qs('inquiries-list').innerHTML = renderAlert('error', error.message || 'Unable to load inquiries.');
      qs('inquiries-summary').textContent = 'Inquiry list unavailable';
    });
  }

  function loadInquiryDetail(id, silent) {
    state.selectedId = Number(id) || 0;
    if (!silent) {
      state.selected = null;
      renderInquiryDetail();
    }
    requestInquiry('admin-get-inquiry', { method: 'GET', query: { id: state.selectedId } }).then(function (data) {
      state.selected = data.inquiry || null;
      renderInquiryList();
      renderInquiryDetail();
      if (state.selected && state.selected.status === 'read') {
        state.items = state.items.map(function (item) {
          return item.id === state.selected.id ? Object.assign({}, item, { status: 'read' }) : item;
        });
        renderInquiryList();
      }
    }).catch(function (error) {
      qs('inquiry-detail').innerHTML = renderAlert('error', error.message || 'Unable to load this inquiry.');
    });
  }

  function saveInquiryStatus(id) {
    requestInquiry('admin-update-inquiry', {
      method: 'POST',
      body: { id: Number(id), status: val('inquiry-status-select') }
    }).then(function () {
      showAlert('inquiry-detail-alert', 'success', 'Inquiry status updated.');
      loadInquiryDetail(id, true);
      loadInquiries(false);
    }).catch(function (error) {
      showAlert('inquiry-detail-alert', 'error', error.message || 'Status could not be updated.');
    });
  }

  function deleteInquiry(id) {
    if (!confirm('Delete this inquiry and all of its notes and replies?')) return;
    var confirmation = window.prompt('Type DELETE to confirm removal of this inquiry.');
    if (confirmation !== 'DELETE') return;
    requestInquiry('admin-delete-inquiry', {
      method: 'POST',
      body: { id: Number(id), confirm_delete: 'DELETE' }
    }).then(function () {
      state.selectedId = 0;
      state.selected = null;
      loadInquiries(false);
      renderInquiryDetail();
    }).catch(function (error) {
      showAlert('inquiry-detail-alert', 'error', error.message || 'Inquiry could not be deleted.');
    });
  }

  function replyInquiry(id) {
    requestInquiry('admin-reply-inquiry', {
      method: 'POST',
      body: {
        id: Number(id),
        admin_name: localAdminName(),
        subject: val('inquiry-reply-subject'),
        message: val('inquiry-reply-message'),
        comment_body: val('inquiry-reply-note'),
        comment_visibility: val('inquiry-reply-visibility') || 'internal'
      }
    }).then(function (data) {
      showAlert('inquiry-detail-alert', 'success', data.message || 'Reply saved.');
      loadInquiryDetail(id, true);
      loadInquiries(false);
    }).catch(function (error) {
      showAlert('inquiry-detail-alert', 'error', error.message || 'Reply could not be saved.');
    });
  }

  function commentInquiry(id) {
    requestInquiry('admin-add-inquiry-comment', {
      method: 'POST',
      body: {
        id: Number(id),
        admin_name: localAdminName(),
        comment_body: val('inquiry-comment-body'),
        visibility: val('inquiry-comment-visibility') || 'internal'
      }
    }).then(function (data) {
      showAlert('inquiry-detail-alert', 'success', data.message || 'Comment added.');
      loadInquiryDetail(id, true);
      setVal('inquiry-comment-body', '');
    }).catch(function (error) {
      showAlert('inquiry-detail-alert', 'error', error.message || 'Comment could not be saved.');
    });
  }

  function bindInquiryFilters() {
    var search = qs('inquiry-search');
    var status = qs('inquiry-filter');
    if (search) {
      search.addEventListener('input', function () {
        state.search = String(search.value || '').trim();
        loadInquiries(true);
      });
    }
    if (status) {
      status.addEventListener('change', function () {
        state.status = status.value || 'all';
        loadInquiries(true);
      });
    }
  }

  function extendShowSection() {
    var baseShowSection = showSection;
    showSection = function (name) {
      baseShowSection(name);
      if (name === 'settings') prefillInquirySettings();
      if (name === 'inquiries') {
        state.search = val('inquiry-search');
        state.status = val('inquiry-filter') || 'all';
        loadInquiries(false);
      }
    };
  }

  function extendSetNav() {
    var baseSetNav = setNav;
    setNav = function (name) {
      baseSetNav(name);
      var el = qs('nav-inquiries');
      if (!el) return;
      var active = name === 'inquiries';
      el.classList.toggle('p', active);
      el.classList.toggle('g', !active);
      el.setAttribute('aria-pressed', String(active));
    };
  }

  function extendRunAction() {
    var baseRunAction = runAction;
    runAction = function (action, target) {
      if (action === 'save-inquiry-settings') return saveInquirySettings();
      if (action === 'refresh-inquiries') return loadInquiries(false);
      if (action === 'open-inquiry') return loadInquiryDetail(target.getAttribute('data-id'));
      if (action === 'inquiry-page') {
        state.meta.page = Math.max(1, Number(target.getAttribute('data-page')) || 1);
        return loadInquiries(false);
      }
      if (action === 'save-inquiry-status') return saveInquiryStatus(target.getAttribute('data-id'));
      if (action === 'delete-inquiry') return deleteInquiry(target.getAttribute('data-id'));
      if (action === 'reply-inquiry') return replyInquiry(target.getAttribute('data-id'));
      if (action === 'comment-inquiry') return commentInquiry(target.getAttribute('data-id'));
      return baseRunAction(action, target);
    };
  }

  document.addEventListener('DOMContentLoaded', function () {
    extendSetNav();
    extendShowSection();
    extendRunAction();
    bindInquiryFilters();
    prefillInquirySettings();
    if (typeof isAuthenticated === 'function' && isAuthenticated() && load('admin_last_section') === 'inquiries') {
      showSection('inquiries');
    }
  });
})();


