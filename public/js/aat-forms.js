/**
 * Localize HTML5 "Please fill out this field" for Romanian GLPI sessions.
 * Also kick native Location setupAjaxDropdown early on transfer destination.
 * Transfer select-all / bulk container panel; dashboard Search chrome hide.
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
   * Covers transfer destination + report location/user/container ajax dropdowns.
   * Safe no-op when already select2-hidden-accessible.
   */
  function aatKickDestLocationDropdowns() {
    if (typeof setupAjaxDropdown !== 'function' || !window.select2_configs) {
      return;
    }
    var wraps = document.querySelectorAll(
      '.aat-location-dropdown, .aat-report-dropdown, .aat-container-dropdown'
    );
    for (var i = 0; i < wraps.length; i++) {
      var sel = wraps[i].querySelector('select.form-select, select');
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
   * Transfer forms: select-all + validate mixed containers
   * (checked → bottom bulk picker; unchecked → per-row dropdown).
   */
  function aatReadBulkContainerId(form) {
    var bulk = form.querySelector('[name="bulk_container_id"]');
    if (!bulk) {
      return 0;
    }
    return parseInt(bulk.value || '0', 10) || 0;
  }

  function aatReadRowContainerId(row) {
    if (!row) {
      return 0;
    }
    var sel = row.querySelector('select[name^="container["]');
    if (!sel) {
      return 0;
    }
    return parseInt(sel.value || '0', 10) || 0;
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
    // Do NOT rewrite .aat-check-all from row checks — select-all stays as the
    // user left it until they click it again.
    var panel = form.querySelector('.aat-transfer-bulk-panel');
    if (!panel) {
      return;
    }
    if (checked > 0) {
      panel.hidden = false;
      panel.removeAttribute('hidden');
      panel.classList.add('is-visible');
      panel.style.display = '';
    } else {
      panel.hidden = true;
      panel.setAttribute('hidden', 'hidden');
      panel.classList.remove('is-visible');
      panel.style.display = '';
    }
  }

  function aatIsTransferCheckForm(form) {
    return !!(form && form.classList && (
      form.classList.contains('aat-transfer-validate-form')
      || form.classList.contains('aat-transfer-create-form')
      || form.classList.contains('aat-transfer-edit-form')
    ));
  }

  function aatApplyCheckAll(form, on) {
    var boxes = form.querySelectorAll('.aat-item-check');
    for (var i = 0; i < boxes.length; i++) {
      boxes[i].checked = !!on;
    }
    var master = form.querySelector('.aat-check-all');
    if (master) {
      master.checked = !!on;
      master.indeterminate = false;
    }
    if (form.classList.contains('aat-transfer-validate-form')) {
      aatSyncTransferBulkPanel(form);
    }
  }

  document.addEventListener('change', function (e) {
    var el = e.target;
    if (!el || !el.classList) {
      return;
    }
    var form = el.closest('form');
    if (!aatIsTransferCheckForm(form)) {
      return;
    }
    if (el.classList.contains('aat-check-all')) {
      aatApplyCheckAll(form, !!el.checked);
      return;
    }
    // Individual row check: update bulk panel only; leave select-all alone.
    if (el.classList.contains('aat-item-check')
        && form.classList.contains('aat-transfer-validate-form')) {
      aatSyncTransferBulkPanel(form);
    }
  }, true);

  // Clicking the validate row (not inputs) toggles selection.
  document.addEventListener('click', function (e) {
    var row = e.target.closest('.aat-transfer-validate-form tr.aat-item-row');
    if (!row) {
      return;
    }
    if (e.target.closest('input, a, button, select, label, .select2-container, .aat-row-container')) {
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
    var selectedCount = 0;
    for (var i = 0; i < boxes.length; i++) {
      var row = boxes[i].closest('tr');
      if (boxes[i].checked) {
        selectedCount++;
      } else if (aatReadRowContainerId(row) <= 0) {
        e.preventDefault();
        window.alert(form.getAttribute('data-aat-row-missing')
          || 'Each unselected item needs its own destination container.');
        return;
      }
    }
    if (selectedCount > 0 && aatReadBulkContainerId(form) <= 0) {
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
   * Dashboard Search embeds: hide download / refresh / columns / map only.
   * Keep search-by, saved searches, column sort, and the Search submit button.
   */
  function aatIsSearchSubmitControl(n) {
    if (!n) {
      return false;
    }
    var name = (n.getAttribute && n.getAttribute('name')) || '';
    var type = (n.getAttribute && n.getAttribute('type')) || '';
    var cls = (n.className && String(n.className)) || '';
    if (name === 'search' || name === 'find' || type === 'submit') {
      // Submit inside the criteria form must stay clickable.
      if (n.closest && (n.closest('form[name="search"]')
          || n.closest('.search-form')
          || n.closest('.aat-dashboard-search .card-body')
          || n.closest('.searchcriteria'))) {
        return true;
      }
    }
    if (/\bbtn-search\b|\bsubmit-search\b/i.test(cls)) {
      return true;
    }
    if (n.querySelector && n.querySelector('.ti-search, .fa-search')) {
      return true;
    }
    return false;
  }

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
      'ti-map', 'ti-table', 'ti-adjustments', 'ti-adjustments-horizontal'
    ];
    // Narrow titles only — bare "map"/"table"/"Select" broke Search submit.
    var titleRe = /^(refresh|download|export|afficher|télécharger|actualiser)$/i;
    var titlePhraseRe = /select\s+(default\s+)?items(\s+to\s+show)?|show\s+as\s+(map|table)|as[_\s-]?map/i;

    for (var s = 0; s < scopes.length; s++) {
      var scope = scopes[s];
      if (!scope || !scope.querySelectorAll) {
        continue;
      }
      var secondary = scope.querySelectorAll(
        '.secondary-controls, .display_prefs_btn, .display-prefs,'
        + ' .export_to_dropdown, #export_to_dropdown, .export_dropdown'
      );
      for (var i = 0; i < secondary.length; i++) {
        secondary[i].style.setProperty('display', 'none', 'important');
      }
      var nodes = scope.querySelectorAll('a, button, .btn, [data-bs-toggle]');
      for (var j = 0; j < nodes.length; j++) {
        var n = nodes[j];
        if (aatIsSearchSubmitControl(n)) {
          continue;
        }
        // Never touch primary sort / search-by / saved-search controls
        // (unless they are clearly a chrome icon button).
        var inProtected = !!(n.closest && n.closest('.primary-controls, .saved_searches'));
        if (inProtected) {
          var chromeIcon = false;
          for (var p = 0; p < iconHints.length; p++) {
            if ((n.querySelector && n.querySelector('.' + iconHints[p]))
                || (n.classList && n.classList.contains(iconHints[p]))) {
              chromeIcon = true;
              break;
            }
          }
          if (!chromeIcon) {
            continue;
          }
        }
        var tip = [
          n.getAttribute('title') || '',
          n.getAttribute('aria-label') || '',
          n.getAttribute('data-bs-original-title') || ''
        ].join(' ').trim();
        var href = n.getAttribute('href') || '';
        var nameAttr = n.getAttribute('name') || '';
        var hide = titleRe.test(tip) || titlePhraseRe.test(tip)
          || /as_map|display_type/.test(href + ' ' + nameAttr)
          || nameAttr === 'as_map' || nameAttr === 'display';
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
          n.style.setProperty('display', 'none', 'important');
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

  /**
   * Reports: toggle red * / required filters from the chosen report type.
   * Other filters stay optional but still apply when filled.
   */
  function aatReportReadType(form) {
    var sel = form.querySelector('select[name="type"]');
    if (!sel) {
      return '';
    }
    return String(sel.value || '');
  }

  function aatReportRequiredMap(form) {
    try {
      return JSON.parse(form.getAttribute('data-aat-required') || '{}') || {};
    } catch (e) {
      return {};
    }
  }

  function aatReportFieldValue(form, field) {
    if (field === 'date_from' || field === 'date_to') {
      var d = form.querySelector('[name="' + field + '"]');
      return d ? String(d.value || '').trim() : '';
    }
    var el = form.querySelector('[name="' + field + '"]');
    if (!el) {
      return '';
    }
    return String(el.value || '').trim();
  }

  function aatSyncReportRequiredMarks(form) {
    if (!form) {
      return;
    }
    var type = aatReportReadType(form);
    var map = aatReportRequiredMap(form);
    var needed = (type && map[type]) ? map[type] : [];
    var needSet = {};
    for (var i = 0; i < needed.length; i++) {
      needSet[needed[i]] = true;
    }
    // Period marks when either date is required.
    var needPeriod = !!(needSet.date_from || needSet.date_to);

    var fields = form.querySelectorAll('[data-aat-filter]');
    for (var f = 0; f < fields.length; f++) {
      var box = fields[f];
      var key = box.getAttribute('data-aat-filter') || '';
      var marks = box.querySelectorAll('.aat-req-mark');
      var on = false;
      if (key === 'period') {
        on = needPeriod;
      } else if (key !== 'type') {
        on = !!needSet[key];
      }
      for (var m = 0; m < marks.length; m++) {
        if (on) {
          marks[m].removeAttribute('hidden');
        } else {
          marks[m].setAttribute('hidden', 'hidden');
        }
      }
    }
  }

  function aatValidateReportForm(form) {
    var type = aatReportReadType(form);
    if (!type) {
      window.alert(form.getAttribute('data-aat-msg-type')
        || 'Please select a report type.');
      return false;
    }
    var map = aatReportRequiredMap(form);
    var needed = map[type] || [];
    for (var i = 0; i < needed.length; i++) {
      var field = needed[i];
      // Scoped location is a hidden filled input — OK.
      if (aatReportFieldValue(form, field) === '' || aatReportFieldValue(form, field) === '0') {
        var msgAttr = 'data-aat-msg-location';
        if (field === 'users_id') {
          msgAttr = 'data-aat-msg-user';
        } else if (field === 'containers_id') {
          msgAttr = 'data-aat-msg-container';
        } else if (field === 'date_from' || field === 'date_to') {
          msgAttr = 'data-aat-msg-period';
        }
        window.alert(form.getAttribute(msgAttr) || 'Please fill the required filters.');
        return false;
      }
    }
    return true;
  }

  /**
   * Container filter only appears after a location is chosen; options follow it.
   */
  function aatSyncReportContainerVisibility(form) {
    var wrap = document.getElementById('aat-report-container-wrap');
    if (!wrap || !form) {
      return;
    }
    var loc = form.querySelector('select[name="locations_id"], input[name="locations_id"]');
    var locId = loc ? (parseInt(loc.value || '0', 10) || 0) : 0;
    if (locId > 0) {
      wrap.removeAttribute('hidden');
    } else {
      wrap.setAttribute('hidden', 'hidden');
    }
  }

  function aatInitReportForm() {
    var form = document.getElementById('aat-report-form');
    if (!form) {
      return;
    }
    aatSyncReportRequiredMarks(form);
    aatSyncReportContainerVisibility(form);
    form.addEventListener('change', function (e) {
      var t = e.target;
      if (!t || !t.name) {
        return;
      }
      if (t.name === 'type') {
        aatSyncReportRequiredMarks(form);
      }
      if (t.name === 'locations_id') {
        aatSyncReportContainerVisibility(form);
      }
    });
    // Select2 may not bubble native change on the select in all cases.
    if (window.jQuery) {
      window.jQuery(form).on(
        'change select2:select select2:clear',
        'select[name="type"]',
        function () {
          aatSyncReportRequiredMarks(form);
        }
      );
      window.jQuery(form).on(
        'change select2:select select2:clear',
        'select[name="locations_id"]',
        function () {
          aatSyncReportContainerVisibility(form);
        }
      );
    }
    form.addEventListener('submit', function (e) {
      if (!aatValidateReportForm(form)) {
        e.preventDefault();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', aatInitReportForm);
  } else {
    aatInitReportForm();
  }
})();
