/**
 * com_userreminder Left Sidebar Integration
 *
 * Replaces Joomla's main left sidebar menu with com_userreminder navigation
 * when the component is loaded. Provides a "Back to Main Menu" link at the
 * BOTTOM of the sidebar to restore the original menu without page reload.
 *
 * Pattern: REPLACE the children of <ul class="main-nav">. Do NOT hide Joomla's
 * sidebar and overlay a custom one — that fights against Atum's layout and
 * loses the existing sidebar styling.
 *
 * Build order (do not re-shuffle):
 *   1. Header item            (component name + icon)
 *   2. Header divider         (hidden by default, hook for layout tweaks)
 *   3. Menu items (loop)      (main items + any submenu children)
 *   4. Back-to-Main-Menu item (footer-style escape)
 *
 * Putting Back at the TOP crowds the component header and breaks visual
 * rhythm — treat the build order above as pattern-defining.
 *
 * Supports an optional `children` array on each item to render a submenu
 * under the parent. Parent URL can be '#' (pure grouping) or a real URL.
 * Parent click toggles the submenu's `mm-show` class + `aria-expanded`.
 *
 * Parent groups: per-group expand state follows three rules, checked in order:
 *   1. If a descendant is on the current page (parent's `active` is true),
 *      force the group open so the user can see where they are.
 *   2. Else, honor the user's saved preference from localStorage
 *      (key `userreminder.sidebar.expanded`, keyed by `item.id`
 *      or fallback to `item.label`).
 *   3. Else, if no preference is stored AND no group is active, open the
 *      FIRST parent so the sidebar never lands fully collapsed.
 *
 * Persistence scope: only parent expand/collapse is stored. The
 * "Back to Main Menu" toggle is deliberately NOT persisted — each page
 * rebuilds the component menu fresh so the sidebar state never gets
 * "stuck" in a surprising mode across sessions.
 *
 * Placeholders to replace:
 *   com_userreminder           e.g. com_foobar
 *   userreminder           e.g. foobar  (used in getOptions namespace + CSS hooks + storage key)
 *   Userreminder    e.g. Foobar  (first letter uppercased — internal function name)
 *   fas fa-bell              Font Awesome class for the header item, e.g. fas fa-box
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var config = Joomla.getOptions('userreminder.sidebar');
    if (!config || !config.items) {
        return;
    }

    var mainNav = document.querySelector('ul.main-nav');
    if (!mainNav) {
        return;
    }

    var STORAGE_KEY = 'userreminder.sidebar.expanded';

    function parentKey(item) {
        return item.id || item.label;
    }

    function loadExpandedState() {
        try {
            var raw = window.localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            return (parsed && typeof parsed === 'object') ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    function saveExpandedState(state) {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {
            // quota full or storage disabled — silently skip.
        }
    }

    function persistExpanded(key, isOpen) {
        if (!key) return;
        var state = loadExpandedState() || {};
        state[key] = !!isOpen;
        saveExpandedState(state);
    }

    var originalChildren = [];
    while (mainNav.firstChild) {
        originalChildren.push(mainNav.removeChild(mainNav.firstChild));
    }

    function clearMainNav() {
        while (mainNav.firstChild) {
            mainNav.removeChild(mainNav.firstChild);
        }
    }

    function createMenuItem(href, iconClass, labelText, isActive, onClick, hasChildren, level, isExpanded) {
        var li = document.createElement('li');
        var levelClass = 'item-level-' + (level || 1);
        li.className = 'item ' + levelClass + (isActive ? ' mm-active' : '') + (hasChildren ? ' parent' : '');

        // Atum renders the chevron via a CSS pseudo-element on `.has-arrow` and
        // rotates it based on `aria-expanded`. DO NOT append a <span> chevron
        // here — it produces two chevrons stacked next to each other.
        var a = document.createElement('a');
        a.className = (hasChildren ? 'has-arrow' : 'no-dropdown') + (isActive ? ' mm-active' : '');
        a.href = href;
        a.setAttribute('aria-label', labelText);

        var iconSpan = document.createElement('span');
        iconSpan.className = iconClass + ' icon-fw';
        iconSpan.setAttribute('aria-hidden', 'true');

        var titleSpan = document.createElement('span');
        titleSpan.className = 'sidebar-item-title';
        titleSpan.textContent = labelText;

        a.appendChild(iconSpan);
        a.appendChild(titleSpan);

        if (hasChildren) {
            a.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        }

        li.appendChild(a);

        if (onClick) {
            a.addEventListener('click', onClick);
        }

        return li;
    }

    function createSubmenu(children, expanded) {
        var ul = document.createElement('ul');
        // `collapse-level-1` + `mm-collapse` are Atum's canonical submenu classes
        // so Atum's own CSS handles indentation, show/hide, and chevron rotation.
        // `userreminder-submenu` is a component-only hook (optional).
        ul.className = 'collapse-level-1 mm-collapse userreminder-submenu' + (expanded ? ' mm-show' : '');

        for (var i = 0; i < children.length; i++) {
            var child = children[i];
            ul.appendChild(createMenuItem(
                child.url,
                child.icon,
                child.label,
                !!child.active,
                null,
                false,
                2,
                false
            ));
        }

        return ul;
    }

    function attachParentToggle(anchor, submenuUl, storageKey) {
        anchor.addEventListener('click', function (e) {
            // If the parent has no real destination, the click only toggles.
            // If it has a real URL, the click navigates and the toggle is
            // skipped so the user doesn't lose their intent.
            if (anchor.getAttribute('href') === '#') {
                e.preventDefault();
                var expanded = submenuUl.classList.toggle('mm-show');
                anchor.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                anchor.parentNode.classList.toggle('mm-active', expanded);
                persistExpanded(storageKey, expanded);
            }
        });
    }

    function createDivider(extraClass) {
        var divider = document.createElement('li');
        divider.className = 'divider' + (extraClass ? ' ' + extraClass : '');
        divider.appendChild(document.createElement('span'));
        return divider;
    }

    function setSidebarActive(isActive) {
        var sidebarWrapper = document.getElementById('sidebar-wrapper');
        if (sidebarWrapper) {
            sidebarWrapper.classList.toggle('userreminder-sidebar-active', isActive);
        }
    }

    /**
     * Decide which parent groups should be visually expanded on build.
     * Returns an array parallel to `items`: `true` at index i means
     * "render item[i]'s submenu with mm-show".
     *
     * Note that `anyGroupActive` only counts GROUPS (items with children)
     * as active, not flat items like Dashboard. If a flat item is current,
     * we still want the first-parent default to kick in so the sidebar
     * isn't fully collapsed.
     */
    function computeExpansion(items, storedState) {
        var firstParentIdx  = -1;
        var anyGroupActive  = false;

        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            var hasKids = Array.isArray(it.children) && it.children.length > 0;
            if (!hasKids) continue;
            if (firstParentIdx === -1) firstParentIdx = i;
            if (it.active) anyGroupActive = true;
        }

        return items.map(function (item, idx) {
            var hasChildren = Array.isArray(item.children) && item.children.length > 0;
            if (!hasChildren) return false;
            if (item.active) return true;

            var key = parentKey(item);
            if (storedState && Object.prototype.hasOwnProperty.call(storedState, key)) {
                return !!storedState[key];
            }

            return !anyGroupActive && idx === firstParentIdx;
        });
    }

    function buildUserreminderSidebar() {
        clearMainNav();

        // 1. Header: first visual anchor inside the sidebar.
        var header = createMenuItem('index.php?option=com_userreminder', 'fas fa-bell', config.headerLabel, false, null, false, 1, false);
        header.classList.add('userreminder-header');
        mainNav.appendChild(header);

        // 2. Header divider (display:none by default; a CSS hook for custom layouts).
        mainNav.appendChild(createDivider('userreminder-header-divider'));

        // 3. Menu items — primary navigation.
        var storedState = loadExpandedState();
        var expansion   = computeExpansion(config.items, storedState);

        for (var i = 0; i < config.items.length; i++) {
            var item = config.items[i];
            var hasChildren = Array.isArray(item.children) && item.children.length > 0;
            var isExpanded  = !!expansion[i];

            var parentLi = createMenuItem(
                item.url || '#',
                item.icon,
                item.label,
                !!item.active,
                null,
                hasChildren,
                1,
                isExpanded
            );
            mainNav.appendChild(parentLi);

            if (hasChildren) {
                var submenu = createSubmenu(item.children, isExpanded);
                parentLi.appendChild(submenu);
                attachParentToggle(parentLi.querySelector('a'), submenu, parentKey(item));
            }
        }

        // 4. Back-to-Main-Menu at the bottom — footer-style escape hatch.
        // DO NOT move this above the menu items: at the top it crowds the
        // header and breaks visual rhythm. Use `margin-top` on the
        // `.userreminder-back-item` class (see helper CSS) for spacing
        // instead of a divider.
        var backItem = createMenuItem('#', 'icon-arrow-left', config.backLabel, false, function (e) {
            e.preventDefault();
            restoreOriginalMenu();
        }, false, 1, false);
        backItem.classList.add('userreminder-back-item');
        mainNav.appendChild(backItem);

        setSidebarActive(true);
    }

    function restoreOriginalMenu() {
        clearMainNav();

        var jrItem = createMenuItem('#', 'fas fa-bell', config.headerLabel, false, function (e) {
            e.preventDefault();
            originalChildren = [];
            while (mainNav.firstChild) {
                var child = mainNav.removeChild(mainNav.firstChild);
                if (child.nodeType === 1 && child.classList.contains('userreminder-injected')) {
                    continue;
                }
                originalChildren.push(child);
            }
            buildUserreminderSidebar();
        }, false, 1, false);
        jrItem.classList.add('userreminder-switch-item', 'userreminder-injected');
        mainNav.appendChild(jrItem);

        mainNav.appendChild(createDivider('userreminder-injected'));

        for (var i = 0; i < originalChildren.length; i++) {
            mainNav.appendChild(originalChildren[i]);
        }

        setSidebarActive(false);
    }

    buildUserreminderSidebar();

    // Anti-flicker CSS from PHP hides ul.main-nav until we've swapped in our menu
    mainNav.style.visibility = 'visible';
});
