/* Dignity Jewels Affiliate — small UI helpers */
(function () {
  'use strict';

  // Clickable table rows
  document.addEventListener('click', function (e) {
    var tr = e.target.closest('tr[data-href]');
    if (tr && !e.target.closest('a, button, input, select, textarea, form, label')) {
      window.location = tr.getAttribute('data-href');
    }
  });

  // Confirm dialogs for dangerous actions
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); return; }
    // prevent double submit
    var btn = e.target.querySelector('button[type=submit]:not([data-no-lock])');
    if (btn && !e.defaultPrevented) {
      setTimeout(function () { btn.disabled = true; }, 0);
    }
  });

  // Copy to clipboard
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]');
    if (!b) return;
    e.preventDefault();
    var text = b.getAttribute('data-copy');
    var done = function () {
      var old = b.innerHTML;
      b.innerHTML = '<i class="bi bi-check2"></i> Copied';
      setTimeout(function () { b.innerHTML = old; }, 1500);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done);
    } else {
      var ta = document.createElement('textarea');
      ta.value = text; document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); done(); } catch (err) {}
      document.body.removeChild(ta);
    }
  });

  // Native share
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-share]');
    if (!b) return;
    var url = b.getAttribute('data-share');
    var text = b.getAttribute('data-share-text') || '';
    if (navigator.share) {
      e.preventDefault();
      navigator.share({ title: 'Dignity Jewels', text: text, url: url }).catch(function () {});
    }
  });

  // Password visibility toggle
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-toggle-pw]');
    if (!b) return;
    var input = document.getElementById(b.getAttribute('data-toggle-pw'));
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
    b.querySelector('i').className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
  });

  // Auto-dismiss success alerts
  setTimeout(function () {
    document.querySelectorAll('.alert-success.alert-dismissible').forEach(function (a) {
      if (window.bootstrap) bootstrap.Alert.getOrCreateInstance(a).close();
    });
  }, 5000);
})();

// Phone country-code picker: show short label (flag + dial code) for the selected country
document.addEventListener('change', function (e) {
  var s = e.target.closest('[data-cc-select]');
  if (!s) return;
  var o = s.options[s.selectedIndex];
  var label = s.parentNode.querySelector('.cc-label');
  if (o && label) label.textContent = o.getAttribute('data-short');
  var input = s.closest('.phone-group').querySelector('input[type=tel]');
  if (input) input.focus();
});

// After WhatsApp / call is opened for a client, refresh so the status (New → Contacted) and activity show
document.addEventListener('click', function (e) {
  var a = e.target.closest('a[data-contact]');
  if (!a) return;
  setTimeout(function () { window.location.reload(); }, 1500);
});

// Searchable select: <select data-searchable> becomes a type-to-search box (the real select stays for form submit)
(function () {
  function norm(s) { return (s || '').toLowerCase().replace(/[^a-z0-9@.+ ]/g, ' ').replace(/\s+/g, ' ').trim(); }
  function digits(s) { return (s || '').replace(/\D/g, ''); }

  function enhance(sel) {
    if (sel.dataset.searchReady) return;
    sel.dataset.searchReady = '1';
    var wrap = document.createElement('div');
    wrap.className = 'ss-wrap';
    sel.parentNode.insertBefore(wrap, sel);
    wrap.appendChild(sel);
    sel.classList.add('ss-native');
    sel.tabIndex = -1;

    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control ss-input';
    input.autocomplete = 'off';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');
    input.placeholder = sel.dataset.placeholder || 'Type to search…';
    var clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'ss-clear';
    clear.setAttribute('aria-label', 'Clear');
    clear.innerHTML = '<i class="bi bi-x-lg"></i>';
    var list = document.createElement('div');
    list.className = 'ss-list';
    list.setAttribute('role', 'listbox');
    wrap.appendChild(input);
    wrap.appendChild(clear);
    wrap.appendChild(list);

    var items = Array.prototype.filter.call(sel.options, function (o) { return !o.disabled; }).map(function (o) {
      return { value: o.value, text: o.text, key: norm(o.text), dig: digits(o.text) };
    });
    var active = -1, shown = [];

    function current() {
      var o = sel.options[sel.selectedIndex];
      return o && o.value !== '' ? o.text : '';
    }
    function sync() {
      input.value = current();
      wrap.classList.toggle('has-value', !!input.value);
    }
    function render(q) {
      var nq = norm(q), dq = digits(q);
      var words = nq ? nq.split(' ') : [];
      shown = items.filter(function (it) {
        if (!q) return true;
        if (dq.length >= 3 && it.dig.indexOf(dq) !== -1) return true;
        return words.every(function (w) { return it.key.indexOf(w) !== -1; });
      }).slice(0, 50);
      list.innerHTML = '';
      if (!shown.length) {
        list.innerHTML = '<div class="ss-empty">No matches</div>';
      }
      shown.forEach(function (it, i) {
        var d = document.createElement('div');
        d.className = 'ss-item' + (it.value === '' ? ' ss-muted' : '') + (it.value === sel.value ? ' selected' : '');
        d.setAttribute('role', 'option');
        d.textContent = it.text;
        d.addEventListener('mousedown', function (e) { e.preventDefault(); pick(it); });
        list.appendChild(d);
      });
      active = shown.length ? 0 : -1;
      highlight();
    }
    function highlight() {
      Array.prototype.forEach.call(list.querySelectorAll('.ss-item'), function (el, i) {
        el.classList.toggle('active', i === active);
        if (i === active) el.scrollIntoView({ block: 'nearest' });
      });
    }
    function open() { wrap.classList.add('open'); input.setAttribute('aria-expanded', 'true'); }
    function close() { wrap.classList.remove('open'); input.setAttribute('aria-expanded', 'false'); sync(); }
    function pick(it) {
      sel.value = it.value;
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      close();
      input.blur();
    }

    input.addEventListener('focus', function () { input.select(); render(''); open(); });
    input.addEventListener('input', function () { render(input.value); open(); });
    input.addEventListener('blur', function () { setTimeout(close, 120); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); if (!wrap.classList.contains('open')) { render(''); open(); } active = Math.min(active + 1, shown.length - 1); highlight(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); highlight(); }
      else if (e.key === 'Enter') { if (wrap.classList.contains('open') && shown[active]) { e.preventDefault(); pick(shown[active]); } }
      else if (e.key === 'Escape') { close(); input.blur(); }
    });
    clear.addEventListener('click', function () {
      sel.value = '';
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      sync();
      input.focus();
    });
    // Browser validation: show message on the visible box
    sel.addEventListener('invalid', function () { input.classList.add('is-invalid'); input.focus(); });
    sel.addEventListener('change', function () { input.classList.remove('is-invalid'); });
    sync();
  }

  document.querySelectorAll('select[data-searchable]').forEach(enhance);
})();

// Smooth-scroll to a section (used by "Edit" buttons); also on page load when the URL has that #hash
(function () {
  function go(sel) {
    var el = document.querySelector(sel);
    if (!el) return;
    setTimeout(function () {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      el.classList.add('flash-target');
      setTimeout(function () { el.classList.remove('flash-target'); }, 1600);
      var f = el.querySelector('input:not([type=hidden]), textarea');
      if (f) f.focus({ preventScroll: true });
    }, 250);
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-scroll-to]');
    if (a) go(a.getAttribute('data-scroll-to'));
  });
  if (location.hash === '#details' || location.hash === '#editBox') go(location.hash);
})();

// Instant "already registered?" check on the client / referral phone field
(function () {
  document.querySelectorAll('.dup-msg[data-dup-check]').forEach(function (box) {
    var mode = box.getAttribute('data-dup-check');          // client | agent | admin
    var group = box.parentNode.querySelector('.phone-group');
    if (!group) return;
    var input = group.querySelector('input[type=tel]');
    var cc = group.querySelector('select[data-cc-select]');
    var form = box.closest('form');
    var submit = form ? form.querySelector('button.btn-lg, button[type=submit]') : null;
    var timer = null, last = '';

    function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
    function setBlocked(on) {
      if (!submit) return;
      submit.disabled = on;
      submit.classList.toggle('opacity-50', on);
    }
    function show(html, cls) {
      box.innerHTML = html ? '<div class="alert ' + cls + ' small mt-2 mb-0">' + html + '</div>' : '';
    }
    function check() {
      var digits = (input.value || '').replace(/\D/g, '');
      var key = (cc ? cc.value : '') + '|' + digits;
      if (digits.length < 6) { last = ''; show(''); setBlocked(false); return; }
      if (key === last) return;
      last = key;
      var url = box.getAttribute('data-dup-url') + '?cc=' + encodeURIComponent(cc ? cc.value : '') + '&phone=' + encodeURIComponent(input.value);
      fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
        if (key !== last) return; // user kept typing
        if (!d.exists) { show(''); setBlocked(false); return; }
        if (mode === 'client') {
          show('<i class="bi bi-check-circle me-1"></i><strong>Your details already exist with us.</strong> Our team will contact you soon — there is no need to submit again.', 'alert-success');
          setBlocked(true);
        } else if (mode === 'agent') {
          show(d.mine
            ? '<i class="bi bi-info-circle me-1"></i>You have already referred this client — they are in your referrals list.'
            : '<i class="bi bi-exclamation-triangle me-1"></i>This client has already been referred by another agent, so they cannot be referred again.', 'alert-warning');
          setBlocked(true);
        } else {
          show('<i class="bi bi-exclamation-triangle me-1"></i>This client already exists: <strong>' + esc(d.name) + '</strong>' +
               (d.agent ? ' (referred by ' + esc(d.agent) + ')' : ' (direct client)') +
               (d.url ? ' — <a class="alert-link" href="' + esc(d.url) + '">open client</a>' : ''), 'alert-warning');
          setBlocked(true);
        }
      }).catch(function () { /* network issue: let the server-side check handle it */ });
    }
    function later() { clearTimeout(timer); timer = setTimeout(check, 500); }
    input.addEventListener('input', later);
    input.addEventListener('blur', check);
    if (cc) cc.addEventListener('change', function () { last = ''; check(); });
    if (input.value) check();
  });
})();
