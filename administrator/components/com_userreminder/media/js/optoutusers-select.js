/**
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Staging list for the opt-out "Select Users" toolbar modal (batch-style, see
 * com_content default_batch_body.php).
 *
 * The modal body lives in a <template> tag, so its content is inert until the
 * Joomla dialog clones it into the live DOM. This script therefore never
 * caches nodes: every handler queries the live document at event time.
 *
 * Picks arrive as `joomla:content-select` messages posted by the com_users
 * modal iframe (core modal-content-select script) and are staged as hidden
 * userids[] inputs — a separate name from the list cid[] checkboxes so the
 * two toolbar actions never interfere.
 *
 * No custom CSS: staged rows use core table/button classes only.
 */
(() => {
  const pickField = 'userreminder_optout_pick';
  const inputName = 'userids[]';

  const liveTbody = () => document.getElementById('userreminder-picked-users');

  const stagedIds = () => new Set(
    [...document.querySelectorAll('input[name="' + inputName + '"]')].map((input) => input.value)
  );

  const updateState = () => {
    const empty = document.getElementById('userreminder-picked-empty');

    if (empty) {
      empty.style.display = document.querySelector('input[name="' + inputName + '"]') ? 'none' : '';
    }
  };

  // Delegated: the staging rows only exist once the dialog is open.
  document.addEventListener('click', (event) => {
    const remove = event.target.closest('[data-picked-remove]');

    if (!remove) {
      return;
    }

    const row = remove.closest('tr');

    if (row) {
      row.remove();
    }

    updateState();
  });

  window.addEventListener('message', (event) => {
    if (event.origin !== window.location.origin) {
      return;
    }

    const data = event.data || {};

    if (data.messageType !== 'joomla:content-select' || data.contentType !== 'com_users.user') {
      return;
    }

    if (data.userField && data.userField !== pickField) {
      return;
    }

    const id = String(data.id || '');

    if (id === '') {
      return;
    }

    const tbody = liveTbody();

    if (!tbody || stagedIds().has(id)) {
      return;
    }

    const row = document.createElement('tr');

    const nameCell = document.createElement('td');
    nameCell.textContent = data.name || id;

    const idCell = document.createElement('td');
    idCell.className = 'text-end';
    idCell.textContent = id;

    const actionCell = document.createElement('td');
    actionCell.className = 'text-end';

    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = inputName;
    hidden.value = id;
    actionCell.appendChild(hidden);

    const removeButton = document.createElement('button');
    removeButton.type = 'button';
    removeButton.className = 'btn btn-sm btn-danger';
    removeButton.setAttribute('data-picked-remove', '');

    const userLabel = data.name || id;
    const removeLabel = (window.Joomla && Joomla.Text && Joomla.Text._)
      ? Joomla.Text._('COM_USERREMINDER_OPTOUT_REMOVE_PICKED').replace('%s', userLabel)
      : userLabel;
    removeButton.setAttribute('aria-label', removeLabel);
    removeButton.title = removeLabel;

    const icon = document.createElement('span');
    icon.className = 'icon-trash';
    icon.setAttribute('aria-hidden', 'true');
    removeButton.appendChild(icon);
    actionCell.appendChild(removeButton);

    row.appendChild(nameCell);
    row.appendChild(idCell);
    row.appendChild(actionCell);

    tbody.appendChild(row);
    updateState();
  });
})();
