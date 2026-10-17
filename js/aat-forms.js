/**
 * Localize HTML5 "Please fill out this field" for Romanian GLPI sessions.
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
  if (!msg) {
    return;
  }

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
})();
