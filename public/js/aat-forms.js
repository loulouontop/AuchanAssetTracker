/**
 * Localize HTML5 "Please fill out this field" for Romanian GLPI sessions.
 * Also kick native Location setupAjaxDropdown early on transfer destination.
 */
(function () {
  function aatRequiredMessage() {
    var lang = (document.documentElement.getAttribute('lang')
      || (window.CFG_GLPI && window.CFG_GLPI.language)
      || '').toLowerCase();
    if (lang.indexOf('ro') === 0) {
      return 'Completați acest câmp';
    }
    return '';
  }

  var msg = aatRequiredMessage();
  if (msg) {
    document.addEventListener('invalid', function (e) {
      var el = e.target;
      if (!el || !el.validity || !el.setCustomValidity) {
        return;
      }
      if (el.validity.valueMissing) {
        el.setCustomValidity(msg);
      }
    }, true);

    document.addEventListener('input', function (e) {
      var el = e.target;
      if (el && el.setCustomValidity) {
        el.setCustomValidity('');
      }
    }, true);

    document.addEventListener('change', function (e) {
      var el = e.target;
      if (el && el.setCustomValidity) {
        el.setCustomValidity('');
      }
    }, true);
  }

  /**
   * If GLPI deferred setupAjaxDropdown, bind Select2 as soon as configs + select exist.
   * Safe no-op when already select2-hidden-accessible.
   */
  function aatKickDestLocationDropdowns() {
    if (typeof setupAjaxDropdown !== 'function' || !window.select2_configs) {
      return;
    }
    var wraps = document.querySelectorAll('.aat-location-dropdown');
    for (var i = 0; i < wraps.length; i++) {
      var sel = wraps[i].querySelector('select.form-select');
      if (!sel || !sel.id) {
        continue;
      }
      if (sel.classList.contains('select2-hidden-accessible')) {
        continue;
      }
      var cfg = window.select2_configs[sel.id];
      if (!cfg) {
        continue;
      }
      try {
        setupAjaxDropdown(cfg);
      } catch (e) {
        // ignore — GLPI ready handler may still run
      }
    }
  }

  var tries = 0;
  var timer = setInterval(function () {
    aatKickDestLocationDropdowns();
    if (++tries > 80) {
      clearInterval(timer);
    }
  }, 25);

  if (window.jQuery) {
    window.jQuery(aatKickDestLocationDropdowns);
  } else {
    document.addEventListener('DOMContentLoaded', aatKickDestLocationDropdowns);
  }

  /**
   * Transfer validation: shared container dropdown appears only when items
   * are selected; backend applies that one container to all selected IDs.
   */
  function aatReadBulkContainerId(form) {
    var bulk = form.querySelector('[name="bulk_container_id"]');
    if (!bulk) {
      return 0;
    }
    return parseInt(bulk.value || '0', 10) || 0;
  }

  function aatSyncTransferBulkPanel(form) {
    if (!form) {
      return;
    }
    var boxes = form.querySelectorAll('.aat-item-check');
    var checked = 0;
    for (var i = 0; i < boxes.length; i++) {
      var row = boxes[i].closest('tr');
      if (boxes[i].checked) {
        checked++;
        if (row) {
          row.classList.add('is-selected');
        }
      } else if (row) {
        row.classList.remove('is-selected');
      }
    }
    var master = form.querySelector('.aat-check-all');
    if (master) {
      master.checked = boxes.length > 0 && checked === boxes.length;
      master.indeterminate = checked > 0 && checked < boxes.length;
    }
    var panel = form.querySelector('.aat-transfer-bulk-panel');
    if (!panel) {
      return;
    }
    if (checked > 0) {
      panel.hidden = false;
      panel.classList.add('is-visible');
    } else {
      panel.hidden = true;
      panel.classList.remove('is-visible');
    }
  }

  document.addEventListener('change', function (e) {
    var el = e.target;
    if (!el || !el.classList) {
      return;
    }
    var form = el.closest('.aat-transfer-validate-form');
    if (!form) {
      return;
    }
    if (el.classList.contains('aat-check-all')) {
      var boxes = form.querySelectorAll('.aat-item-check');
      for (var i = 0; i < boxes.length; i++) {
        boxes[i].checked = !!el.checked;
      }
      aatSyncTransferBulkPanel(form);
      return;
    }
    if (el.classList.contains('aat-item-check')) {
      aatSyncTransferBulkPanel(form);
    }
  });

  // Clicking the row (not just the checkbox) toggles selection.
  document.addEventListener('click', function (e) {
    var row = e.target.closest('.aat-transfer-validate-form tr.aat-item-row');
    if (!row) {
      return;
    }
    if (e.target.closest('input, a, button, select, .select2-container')) {
      return;
    }
    var check = row.querySelector('.aat-item-check');
    if (!check) {
      return;
    }
    check.checked = !check.checked;
    try {
      check.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (err) {
      aatSyncTransferBulkPanel(row.closest('.aat-transfer-validate-form'));
    }
  });

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.classList || !form.classList.contains('aat-transfer-validate-form')) {
      return;
    }
    var boxes = form.querySelectorAll('.aat-item-check');
    var selected = form.querySelectorAll('.aat-item-check:checked');
    if (!selected.length) {
      e.preventDefault();
      window.alert(form.getAttribute('data-aat-select-items')
        || 'Select at least one equipment item.');
      return;
    }
    if (boxes.length && selected.length !== boxes.length) {
      e.preventDefault();
      window.alert(form.getAttribute('data-aat-select-all')
        || 'Select all equipment items and one container to validate the transfer.');
      return;
    }
    if (aatReadBulkContainerId(form) <= 0) {
      e.preventDefault();
      window.alert(form.getAttribute('data-aat-bulk-missing')
        || 'Select a container for the selected equipment.');
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('.aat-transfer-validate-form');
    for (var i = 0; i < forms.length; i++) {
      aatSyncTransferBulkPanel(forms[i]);
    }
  });

  /**
   * Dashboard Search embeds: strip refresh / download / columns / map|table
   * controls that CSS alone may miss across GLPI 11 markup variants.
   */
  function aatHideDashboardSearchChrome(root) {
    root = root || document;
    var scopes = [];
    if (root.querySelectorAll) {
      scopes = Array.prototype.slice.call(
        root.querySelectorAll('.aat-dashboard-search, [data-aat-hide-search-chrome="1"]')
      );
    }
    if (root.classList
        && (root.classList.contains('aat-dashboard-search')
            || root.getAttribute('data-aat-hide-search-chrome') === '1')) {
      scopes = [root];
    }
    var iconHints = [
      'ti-refresh', 'ti-file-download', 'ti-file-export', 'ti-download',
      'ti-map', 'ti-table', 'ti-adjustments', 'ti-adjustments-horizontal',
      'ti-device-floppy', 'ti-bookmark', 'ti-search'
    ];
    var titleRe = /refresh|download|export|map|table|saved\s*search|save\s*current\s*search|search\s*criteria|select.*(item|column|default)|afficher|télécharger|actualiser|recherch/i;

    for (var s = 0; s < scopes.length; s++) {
      var scope = scopes[s];
      if (!scope || !scope.querySelectorAll) {
        continue;
      }
      var secondary = scope.querySelectorAll(
        '.secondary-controls, .primary-controls, .display_prefs_btn, .display-prefs,'
        + ' .export_to_dropdown, #export_to_dropdown, .export_dropdown,'
        + ' form[name="search"], form.search-form, .search-form, .search_form,'
        + ' .saved_searches, .savedsearches, .saved-searches, .search_actions'
      );
      for (var i = 0; i < secondary.length; i++) {
        secondary[i].style.display = 'none';
      }
      var nodes = scope.querySelectorAll('a, button, .btn, [data-bs-toggle]');
      for (var j = 0; j < nodes.length; j++) {
        var n = nodes[j];
        var tip = [
          n.getAttribute('title') || '',
          n.getAttribute('aria-label') || '',
          n.getAttribute('data-bs-original-title') || '',
          n.getAttribute('href') || '',
          n.getAttribute('name') || ''
        ].join(' ');
        var hide = titleRe.test(tip) || /as_map|display_type|export/.test(tip);
        if (!hide) {
          for (var k = 0; k < iconHints.length; k++) {
            if (n.querySelector && n.querySelector('.' + iconHints[k])) {
              hide = true;
              break;
            }
            if (n.classList && n.classList.contains(iconHints[k])) {
              hide = true;
              break;
            }
          }
        }
        if (hide) {
          n.style.display = 'none';
        }
      }
    }
  }

  function aatScheduleHideSearchChrome() {
    aatHideDashboardSearchChrome(document);
    // Search widgets may paint after DOMContentLoaded.
    setTimeout(function () { aatHideDashboardSearchChrome(document); }, 50);
    setTimeout(function () { aatHideDashboardSearchChrome(document); }, 300);
    setTimeout(function () { aatHideDashboardSearchChrome(document); }, 1000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', aatScheduleHideSearchChrome);
  } else {
    aatScheduleHideSearchChrome();
  }
})();
