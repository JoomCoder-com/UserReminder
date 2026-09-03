/*
 * com_userreminder sidebar — in-place replacement of Joomla's <ul.main-nav>.
 *
 * Pattern modelled on the Atum sidebar (the default Joomla 4 admin template).
 * We capture the original <li> children once, render our component's items
 * in their place, and put a "Back to Main Menu" item at the BOTTOM (footer
 * style, not competing with the component header).
 *
 * Items + active state are passed in via Joomla's script options:
 *   Joomla.getOptions('com_userreminder.sidebar.items')
 */
(function () {
    'use strict';

    if (typeof Joomla === 'undefined' || !Joomla.getOptions) {
        return;
    }

    var items = Joomla.getOptions('com_userreminder.sidebar.items', []);
    if (!items.length) {
        return;
    }

    var navList = document.querySelector('ul.main-nav');
    if (!navList) {
        return;
    }

    navList.classList.add('userreminder-rendered');

    var originalChildren = Array.prototype.slice.call(navList.children);
    var headerIcon       = 'fas fa-bell';
    var backIcon         = 'fas fa-chevron-circle-left';

    function buildUserReminderSidebar() {
        // Empty everything.
        while (navList.firstChild) {
            navList.removeChild(navList.firstChild);
        }

        // 1. Header
        var header = document.createElement('li');
        header.className = 'item-level-0 header userreminder-header';
        header.innerHTML = '<a href="index.php?option=com_userreminder&view=cpanel">' +
            '<span class="icon ' + headerIcon + '"></span>' +
            '<span class="sidebar-item-title">User Reminder</span>' +
            '</a>';
        navList.appendChild(header);

        // 2. Items
        items.forEach(function (item) {
            var li = document.createElement('li');
            li.className = 'item-level-1' + (item.active ? ' mm-active' : '');
            li.innerHTML =
                '<a href="' + item.url + '">' +
                '<span class="icon ' + (item.icon || 'fas fa-circle') + '"></span>' +
                '<span class="sidebar-item-title">' + item.label + '</span>' +
                '</a>';
            navList.appendChild(li);
        });

        // 3. "Back to Main Menu" — at the bottom, footer style.
        var back = document.createElement('li');
        back.className = 'item-level-1 userreminder-back-item';
        back.style.marginTop = '1.5rem';
        back.innerHTML =
            '<a href="#" id="userreminder-back-to-main">' +
            '<span class="icon ' + backIcon + '"></span>' +
            '<span class="sidebar-item-title">' +
            Joomla.Text._('COM_USERREMINDER_SIDEBAR_BACK_TO_MAIN_MENU') +
            '</span>' +
            '</a>';
        navList.appendChild(back);

        document.getElementById('userreminder-back-to-main').addEventListener('click', function (e) {
            e.preventDefault();
            restoreOriginal();
        });
    }

    function restoreOriginal() {
        while (navList.firstChild) {
            navList.removeChild(navList.firstChild);
        }
        originalChildren.forEach(function (child) {
            navList.appendChild(child);
        });

        // Re-render on the next click of the UserReminder header link.
        navList.addEventListener('click', function once(ev) {
            var a = ev.target.closest('a');
            if (!a) {
                return;
            }
            if (a.href && a.href.indexOf('option=com_userreminder') !== -1) {
                ev.preventDefault();
                navList.removeEventListener('click', once);
                buildUserReminderSidebar();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', buildUserReminderSidebar);
    } else {
        buildUserReminderSidebar();
    }
})();