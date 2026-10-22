/**
 * SilverShop cms-ui — keyboard / instant submit for the always-visible filter bar
 * ({@link SilverShop\CmsUi\Forms\GridFieldFilterForm}).
 *
 * The bar normally submits via its "Search" button — a GridField_FormAction the admin's stock
 * gridfield handler serialises and POSTs, re-rendering the grid with the active filters. This adds
 * the expected UX on top of that SAME path (no new submit logic):
 *
 *   - Enter in a text / date / etc. field submits the filter — and prevents the surrounding CMS edit
 *     form's own Enter-to-save — by activating the bar's Search button.
 *   - Changing a select / checkbox / radio, or picking a value in a date / time picker, submits instantly.
 *     (Free-text inputs stay Enter-only — they fire `change` on blur, which would submit on every tab-out.)
 *
 * Delegated on `document`, so it keeps working after the gridfield re-renders (PJAX). It activates
 * the button through jQuery when present (the admin binds its gridfield handlers via jQuery.entwine),
 * falling back to a native click. Dependency-free.
 */
(function () {
    function searchButton(el) {
        var form = el && el.closest ? el.closest('.ss-filter-form') : null;
        return form ? form.querySelector('.ss-filter-form__btn--search') : null;
    }

    function submitFilter(btn) {
        if (window.jQuery) {
            window.jQuery(btn).trigger('click');
        } else {
            btn.click();
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.keyCode !== 13) {
            return;
        }
        var t = e.target;
        if (!t || !t.matches || !t.matches(
            '.ss-filter-form input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="submit"]):not([type="button"])'
        )) {
            return;
        }
        var btn = searchButton(t);
        if (btn) {
            e.preventDefault();
            submitFilter(btn);
        }
    });

    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !t.matches || !t.matches(
            '.ss-filter-form select, .ss-filter-form input[type="checkbox"], .ss-filter-form input[type="radio"], ' +
            '.ss-filter-form input[type="date"], .ss-filter-form input[type="datetime-local"], ' +
            '.ss-filter-form input[type="month"], .ss-filter-form input[type="week"], .ss-filter-form input[type="time"]'
        )) {
            return;
        }
        var btn = searchButton(t);
        if (btn) {
            submitFilter(btn);
        }
    });
})();
