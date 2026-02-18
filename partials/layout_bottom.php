</div>
<?php if (isset($_SESSION['teacher_id']) && strpos(trim((string)($bodyClass ?? '')), 'bnhs-hide-nav') === false): ?>
</main>
<?php endif; ?>

<div id="bnhsLoadingOverlay" class="bnhs-loading-overlay d-none" aria-hidden="true">
  <div class="spinner-border text-light" role="status" aria-hidden="true"></div>
  <div class="mt-3 text-white fw-semibold">Loading...</div>
</div>

<div class="toast-container position-fixed end-0 p-3" id="bnhsToastHost" style="z-index: 1080;"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
  (function () {
    function escapeHtml(s) {
      return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    const toastHost = document.getElementById('bnhsToastHost');
    const overlay = document.getElementById('bnhsLoadingOverlay');

    // Sidebar toggle logic
    var sidebar = document.getElementById('bnhsSidebar');
    var sidebarOverlay = document.getElementById('sidebarOverlay');
    var sidebarToggle = document.getElementById('sidebarToggle');

    function openSidebar() {
      if (!sidebar) return;
      sidebar.classList.add('open');
      if (sidebarOverlay) sidebarOverlay.classList.add('open');
      document.body.classList.add('bnhs-sidebar-open');
    }

    function closeSidebar() {
      if (!sidebar) return;
      sidebar.classList.remove('open');
      if (sidebarOverlay) sidebarOverlay.classList.remove('open');
      document.body.classList.remove('bnhs-sidebar-open');
    }

    if (sidebarToggle) {
      sidebarToggle.addEventListener('click', function () {
        if (sidebar && sidebar.classList.contains('open')) {
          closeSidebar();
        } else {
          openSidebar();
        }
      });
    }

    if (sidebarOverlay) {
      sidebarOverlay.addEventListener('click', closeSidebar);
    }

    // Close sidebar on nav link click (mobile)
    if (sidebar) {
      sidebar.querySelectorAll('.bnhs-sidebar-link').forEach(function (link) {
        link.addEventListener('click', function () {
          if (window.innerWidth < 768) closeSidebar();
        });
      });
    }

    window.bnhsToast = function (type, message) {
      if (!toastHost || typeof bootstrap === 'undefined' || !bootstrap.Toast) return;

      const map = { success: 'success', info: 'primary', warning: 'warning', danger: 'danger' };
      const bg = map[String(type || '').toLowerCase()] || 'primary';

      const el = document.createElement('div');
      el.className = 'toast align-items-center text-bg-' + bg + ' border-0';
      el.setAttribute('role', 'alert');
      el.setAttribute('aria-live', 'assertive');
      el.setAttribute('aria-atomic', 'true');
      el.innerHTML =
        '<div class="d-flex">' +
        '<div class="toast-body">' + escapeHtml(message || '') + '</div>' +
        '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
        '</div>';

      toastHost.appendChild(el);

      const t = new bootstrap.Toast(el, { delay: 4200 });
      el.addEventListener('hidden.bs.toast', function () { el.remove(); });
      t.show();
    };

    document.addEventListener('DOMContentLoaded', function () {
      if (typeof window.bnhsToast !== 'function') return;

      const map = [
        { selector: '.alert.alert-success', type: 'success' },
        { selector: '.alert.alert-info', type: 'info' },
        { selector: '.alert.alert-warning', type: 'warning' }
      ];

      map.forEach(function (m) {
        document.querySelectorAll(m.selector).forEach(function (a) {
          if (!a || a.hasAttribute('data-no-toast')) return;
          const msg = (a.textContent || '').trim();
          if (!msg) return;
          window.bnhsToast(m.type, msg);
          if (!a.hasAttribute('data-keep-alert')) {
            a.remove();
          }
        });
      });
    });

    document.addEventListener('click', function (e) {
      const a = e.target && e.target.closest ? e.target.closest('a[data-confirm]') : null;
      if (!a) return;
      const href = a.getAttribute('href') || '';
      if (!href || href === '#') return;

      e.preventDefault();

      const msg = a.dataset.confirm || 'Continue?';
      const title = a.dataset.confirmTitle || 'Confirm';
      const confirmText = a.dataset.confirmOk || 'Continue';
      const cancelText = a.dataset.confirmCancel || 'Cancel';
      const icon = a.dataset.confirmIcon || 'warning';

      if (typeof Swal !== 'undefined' && Swal && Swal.fire) {
        Swal.fire({
          title: title,
          text: msg,
          icon: icon,
          showCancelButton: true,
          confirmButtonText: confirmText,
          cancelButtonText: cancelText,
          reverseButtons: true
        }).then(function (r) {
          if (!r || !r.isConfirmed) return;
          window.location.href = href;
        });
      } else {
        if (window.confirm(msg)) {
          window.location.href = href;
        }
      }
    });

    function showLoading() {
      if (!overlay) return;
      overlay.classList.remove('d-none');
    }

    function hideLoading() {
      if (!overlay) return;
      overlay.classList.add('d-none');
    }

    document.addEventListener('submit', function (e) {
      const form = e.target;
      if (!form || form.nodeName !== 'FORM') return;

      const submitter = e.submitter || null;

      if (!form.dataset.bnhsConfirmed && form.dataset.confirm) {
        e.preventDefault();

        const msg = form.dataset.confirm || 'Are you sure?';
        const title = form.dataset.confirmTitle || 'Confirm';
        const confirmText = form.dataset.confirmOk || 'Yes';
        const cancelText = form.dataset.confirmCancel || 'Cancel';
        const icon = form.dataset.confirmIcon || 'warning';

        if (typeof Swal !== 'undefined' && Swal && Swal.fire) {
          Swal.fire({
            title: title,
            text: msg,
            icon: icon,
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            reverseButtons: true
          }).then(function (r) {
            if (!r || !r.isConfirmed) return;
            form.dataset.bnhsConfirmed = '1';
            if (submitter && typeof form.requestSubmit === 'function') {
              form.requestSubmit(submitter);
            } else {
              if (submitter && submitter.name) {
                const h = document.createElement('input');
                h.type = 'hidden';
                h.name = submitter.name;
                h.value = submitter.value || '';
                form.appendChild(h);
              }
              form.submit();
            }
          });
        } else {
          if (window.confirm(msg)) {
            form.dataset.bnhsConfirmed = '1';
            form.submit();
          }
        }

        return;
      }

      if (e.defaultPrevented) return;
      if (form.hasAttribute('data-no-loading')) return;

      const btns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
      btns.forEach(function (b) {
        if (submitter && b === submitter) return;
        b.setAttribute('data-bnhs-disabled', '1');
        b.disabled = true;
      });
      showLoading();
    });

    window.addEventListener('pageshow', function () {
      hideLoading();
      document.querySelectorAll('[data-bnhs-disabled="1"]').forEach(function (b) {
        b.disabled = false;
        b.removeAttribute('data-bnhs-disabled');
      });
    });

    // Notification polling
    var notifBell = document.getElementById('notifBell');
    var notifBadge = document.getElementById('notifBadge');
    var notifList = document.getElementById('notifList');
    var notifMarkRead = document.getElementById('notifMarkRead');
    var notifPollUrl = (document.querySelector('meta[name="base-url"]') || {}).content;
    if (!notifPollUrl) {
      var scripts = document.querySelectorAll('link[href*="bicos.css"]');
      if (scripts.length) {
        var href = scripts[0].getAttribute('href') || '';
        notifPollUrl = href.replace(/assets\/bicos\.css.*$/, '');
      }
    }
    if (!notifPollUrl) notifPollUrl = '/';

    function fetchNotifications() {
      if (!notifBell) return;
      fetch(notifPollUrl + 'api_poll.php?type=notifications', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (!data || !data.ok) return;

          // Update badge
          var count = data.badge || 0;
          if (notifBadge) {
            notifBadge.textContent = count;
            if (count > 0) {
              notifBadge.classList.remove('d-none');
            } else {
              notifBadge.classList.add('d-none');
            }
          }

          // Update dropdown list
          if (!notifList) return;
          var items = data.items || [];
          if (items.length === 0) {
            notifList.innerHTML = '<div class="dropdown-item-text text-muted small text-center py-3">No notifications</div>';
            return;
          }

          var html = '';
          items.forEach(function(item) {
            var bgClass = item.is_new ? ' bg-light' : '';
            var dotHtml = item.is_new ? '<span class="badge bg-danger me-1" style="width:8px;height:8px;padding:0;border-radius:50%">&nbsp;</span>' : '';
            var badgeClass = 'text-bg-' + (item.badge || 'secondary');
            var timeStr = item.time ? '<div class="text-muted" style="font-size:0.7rem">' + escapeHtml(item.time) + '</div>' : '';
            var url = item.url || '#';

            html += '<a class="dropdown-item small py-2 border-bottom' + bgClass + '" href="' + escapeHtml(url) + '">';
            html += '<div class="d-flex align-items-start gap-1">';
            html += dotHtml;
            html += '<div class="flex-grow-1">';
            html += '<div>' + escapeHtml(item.message || '') + '</div>';
            html += timeStr;
            html += '</div>';
            html += '</div>';
            html += '</a>';
          });

          notifList.innerHTML = html;
        })
        .catch(function() {});
    }

    if (notifMarkRead) {
      notifMarkRead.addEventListener('click', function() {
        fetch(notifPollUrl + 'api_poll.php?type=notifications_seen', { credentials: 'same-origin' })
          .then(function() {
            if (notifBadge) {
              notifBadge.textContent = '0';
              notifBadge.classList.add('d-none');
            }
            // Remove new indicators
            if (notifList) {
              notifList.querySelectorAll('.bg-light').forEach(function(el) { el.classList.remove('bg-light'); });
              notifList.querySelectorAll('.badge.bg-danger').forEach(function(el) {
                if (el.style.width === '8px') el.remove();
              });
            }
          })
          .catch(function() {});
      });
    }

    // Initial fetch + poll every 15 seconds
    if (notifBell) {
      fetchNotifications();
      setInterval(fetchNotifications, 15000);
    }
  })();
</script>
</body>
</html>
