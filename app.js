const dialog = document.querySelector('#record-dialog');
const fundsDialog = document.querySelector('#funds-dialog');
const recordForm = document.querySelector('#record-form');
const dateInput = document.querySelector('#record-date');
const amountInput = document.querySelector('#record-amount');
const notesInput = document.querySelector('#record-notes');
const title = document.querySelector('#dialog-title');
const deleteForm = document.querySelector('#delete-form');
const deleteDate = document.querySelector('#delete-date');

function openRecordDialog(record = {}) {
    recordForm.reset();
    const now = new Date();
    const localDate = [
        now.getFullYear(),
        String(now.getMonth() + 1).padStart(2, '0'),
        String(now.getDate()).padStart(2, '0'),
    ].join('-');
    dateInput.value = record.date || localDate;
    title.textContent = record.result ? 'Edit record' : 'Add a record';
    amountInput.value = record.amount || '';
    notesInput.value = record.notes || '';
    const resultInput = recordForm.querySelector(`input[name="result"][value="${record.result || 'win'}"]`);
    resultInput.checked = true;
    deleteDate.value = record.date || '';
    deleteForm.hidden = !record.result;
    dialog.showModal();
}

document.querySelector('#add-record').addEventListener('click', () => openRecordDialog());

document.querySelector('#add-funds').addEventListener('click', () => {
    fundsDialog.querySelector('form').reset();
    fundsDialog.showModal();
});

document.querySelectorAll('.day-cell[data-date]').forEach((cell) => {
    cell.addEventListener('click', () => {
        openRecordDialog({
            date: cell.dataset.date,
            result: cell.dataset.result,
            amount: cell.dataset.amount,
            notes: cell.dataset.notes,
        });
    });
});

document.querySelectorAll('.close-dialog').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog').close());
});

document.querySelectorAll('dialog').forEach((currentDialog) => {
    currentDialog.addEventListener('click', (event) => {
        if (event.target === currentDialog) currentDialog.close();
    });
});

deleteForm.addEventListener('submit', (event) => {
    if (!window.confirm('Delete this day’s record? This cannot be undone.')) event.preventDefault();
});
