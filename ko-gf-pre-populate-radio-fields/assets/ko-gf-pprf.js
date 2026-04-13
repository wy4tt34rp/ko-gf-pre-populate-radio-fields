(function () {
  // CSS.escape polyfill (basic)
  if (typeof window.CSS === "undefined") window.CSS = {};
  if (typeof window.CSS.escape !== "function") {
    window.CSS.escape = function (value) {
      return String(value).replace(/["\\]/g, "\\$&");
    };
  }

  function key(formId, fieldId) {
    return "ko_gf_pprf_" + formId + "_" + fieldId;
  }

  function getStored(formId, fieldId) {
    try {
      return sessionStorage.getItem(key(formId, fieldId)) || "";
    } catch (e) {
      // Storage may be blocked in some contexts (privacy mode, etc.)
      return "";
    }
  }

  function setStored(formId, fieldId, val) {
    try {
      sessionStorage.setItem(key(formId, fieldId), val);
    } catch (e) {
      // Storage may be blocked; fail silently.
      return;
    }
  }

  function getLabelText(formEl, inputEl) {
    if (!inputEl || !inputEl.id) return "";
    var lbl = formEl.querySelector('label[for="' + CSS.escape(inputEl.id) + '"]');
    return lbl && lbl.textContent ? lbl.textContent.trim() : "";
  }

  function readParent(formEl, parentId, fid) {
    // 1) If parent radios exist on this page:
    var checked = formEl.querySelector('input[name="input_' + parentId + '"]:checked');
    if (checked) {
      return { value: checked.value || "", label: getLabelText(formEl, checked) };
    }

    // 2) If GF carried value as hidden input (sometimes):
    var hidden = formEl.querySelector('input[type="hidden"][name="input_' + parentId + '"]');
    if (hidden && hidden.value) {
      return { value: hidden.value, label: hidden.value };
    }

    // 3) sessionStorage fallback (works across multi-page reloads):
    var v = getStored(fid, parentId);
    return { value: v, label: v };
  }

  function setChildByValueOrLabel(formEl, childId, parentValue, parentLabel) {
    if (!parentValue && !parentLabel) return false;

    // 1) Try exact value match
    if (parentValue) {
      var byVal = formEl.querySelector(
        'input[name="input_' + childId + '"][value="' + CSS.escape(parentValue) + '"]'
      );
      if (byVal) {
        if (!byVal.checked) {
          byVal.checked = true;
          byVal.dispatchEvent(new Event("change", { bubbles: true }));
        }
        return true;
      }
    }

    // 2) Try label match (Miles/Kilometers)
    if (parentLabel) {
      var radios = formEl.querySelectorAll('input[name="input_' + childId + '"]');
      for (var i = 0; i < radios.length; i++) {
        var r = radios[i];
        var lbl = getLabelText(formEl, r);
        if (lbl && lbl.toLowerCase() === parentLabel.toLowerCase()) {
          if (!r.checked) {
            r.checked = true;
            r.dispatchEvent(new Event("change", { bubbles: true }));
          }
          return true;
        }
      }
    }

    return false;
  }

  function applyRules(formEl, fid, rules) {
    rules.forEach(function (rule) {
      var parentId = parseInt(rule.parent_field_id, 10);
      var childId = parseInt(rule.child_field_id, 10);
      if (!parentId || !childId) return;

      var p = readParent(formEl, parentId, fid);
      if (p.value) setStored(fid, parentId, p.value);

      setChildByValueOrLabel(formEl, childId, p.value, p.label);
    });
  }

  function initForm(formId) {
    // Dreamweaver/linter silencer: always reference window.KO_PPRB
    if (typeof window.KO_PPRB === "undefined" || !window.KO_PPRB.rulesByForm) return;

    var fid = parseInt(formId, 10);
    var rules = window.KO_PPRB.rulesByForm[fid];
    if (!fid || !rules || !rules.length) return;

    var formEl = document.getElementById("gform_" + fid);
    if (!formEl) return;

    // Apply immediately on load/render
    applyRules(formEl, fid, rules);

    // Don’t bind twice
    if (formEl.dataset.koGfPprfBound === "1") return;
    formEl.dataset.koGfPprfBound = "1";

    function captureIfParent(target) {
      if (!target || !target.name) return;

      rules.forEach(function (rule) {
        var parentId = parseInt(rule.parent_field_id, 10);
        var childId = parseInt(rule.child_field_id, 10);

        if (target.name === "input_" + parentId) {
          // Give browser a tick to finalize checked state
          setTimeout(function () {
            var p = readParent(formEl, parentId, fid);
            if (p.value) setStored(fid, parentId, p.value);
            setChildByValueOrLabel(formEl, childId, p.value, p.label);
          }, 0);
        }
      });
    }

    // Capture on CHANGE
    formEl.addEventListener("change", function (e) {
      captureIfParent(e.target);
    });

    // Capture on CLICK (covers “click label then immediately click Next” timing)
    formEl.addEventListener("click", function (e) {
      var t = e.target;

      if (t && t.tagName === "LABEL" && t.htmlFor) {
        captureIfParent(formEl.querySelector("#" + CSS.escape(t.htmlFor)));
        return;
      }

      captureIfParent(t);
    });

    // Store/apply right before Next/submit navigation
    formEl.addEventListener("submit", function () {
      applyRules(formEl, fid, rules);
    });

    // Watch for child radios to appear (conditional logic, page transitions, async render)
    var obs = new MutationObserver(function () {
      applyRules(formEl, fid, rules);
    });

    obs.observe(formEl, { childList: true, subtree: true });
  }

  function initAll() {
    if (typeof window.KO_PPRB === "undefined" || !window.KO_PPRB.rulesByForm) return;

    Object.keys(window.KO_PPRB.rulesByForm).forEach(function (fid) {
      initForm(fid);
    });
  }

  document.addEventListener("DOMContentLoaded", initAll);
  document.addEventListener("gform_post_render", function (event, formId) {
    initForm(formId);
  });
})();
