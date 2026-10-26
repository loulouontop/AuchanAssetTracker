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
   * Transfer validation: apply one destination container to all / checked rows.
   */
  function aatReadBulkContainerId(form) {
    var bulk = form.querySelector('[name="bulk_container_id"]');
    if (!bulk) {
      return 0;
    }
    return parseInt(bulk.value || '0', 10) || 0;
  }

  function aatSetRowContainer(form, eid, cid) {
    if (!eid || !cid) {
      return;
    }
    var sel = form.querySelector('select[name="container[' + eid + ']"]');
    if (!sel) {
      return;
    }
    sel.value = String(cid);
    if (window.jQuery) {
      var $sel = window.jQuery(sel);
      if ($sel.hasClass('select2-hidden-accessible')) {
        $sel.val(String(cid)).trigger('change');
      }
    }
    try {
      sel.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (e) {
      // ignore
    }
  }

  function aatApplyBulkContainer(form, checkedOnly) {
    var cid = aatReadBulkContainerId(form);
    if (cid <= 0) {
      window.alert(form.getAttribute('data-aat-bulk-missing')
        || 'Select a container first.');
      return;
    }
    var rows = form.querySelectorAll('tbody tr');
    for (var i = 0; i < rows.length; i++) {
      var row = rows[i];
      var check = row.querySelector('.aat-item-check');
      if (checkedOnly && check && !check.checked) {
        continue;
      }
      var eid = check ? parseInt(check.getAttribute('data-eid') || '0', 10) : 0;
      if (!eid) {
        var named = row.querySelector('select[name^="container["]');
        if (named && named.name) {
          var m = named.name.match(/container\[(\d+)\]/);
          eid = m ? parseInt(m[1], 10) : 0;
        }
      }
      aatSetRowContainer(form, eid, cid);
    }
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.aat-apply-container-all, .aat-apply-container-checked');
    if (!btn) {
      return;
    }
    var form = btn.closest('.aat-transfer-validate-form');
    if (!form) {
      return;
    }
    e.preventDefault();
    aatApplyBulkContainer(form, btn.classList.contains('aat-apply-container-checked'));
  });

  document.addEventListener('change', function (e) {
    var el = e.target;
    if (!el || !el.classList || !el.classList.contains('aat-check-all')) {
      return;
    }
    var form = el.closest('.aat-transfer-validate-form');
    if (!form) {
      return;
    }
    var boxes = form.querySelectorAll('.aat-item-check');
    for (var i = 0; i < boxes.length; i++) {
      boxes[i].checked = !!el.checked;
    }
  });
})();
