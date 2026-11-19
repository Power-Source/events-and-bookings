(() => {
    'use strict';

    const form = document.getElementById('post');
    const repeat = document.getElementById('eab_event-recurring_event');
    const rows = document.getElementById('eab-add-more-rows');
    if (!form || (!repeat && !rows)) return;

    const modes = repeat ? repeat.querySelectorAll('.eab_event_recurrence_mode') : [];
    const interval = document.getElementById('eab_event-repeat_every');
    const toggle = document.getElementById('eab-eab-start_recurrence-button');
    const add = document.getElementById('eab-add-more');
    const slug = document.getElementById('edit-slug-box');
    let recurring = Boolean(repeat && !toggle);

    const dateRows = () => [...rows.querySelectorAll('.eab-section-block')];
    let nextIndex = rows ? Math.max(-1, ...dateRows().map(row =>
        Number(row.querySelector('.psource_event_start').id.split('_').pop())
    )) + 1 : 0;

    function sync() {
        modes.forEach(mode => {
            mode.style.display = recurring && mode.id === `eab_event-repeat_interval-${interval.value}` ? '' : 'none';
        });
        if (repeat) {
            repeat.style.display = recurring ? '' : 'none';
            repeat.querySelectorAll('input, select').forEach(field => {
                const mode = field.closest('.eab_event_recurrence_mode');
                field.disabled = !recurring || Boolean(mode && mode.style.display === 'none');
            });
        }
        if (slug) slug.hidden = recurring;
        if (!rows) return;
        rows.hidden = recurring;
        add.style.display = recurring ? 'none' : 'block';
        const all = dateRows();
        all.forEach((row, index) => {
            row.querySelector('.eab-section-heading').hidden = all.length === 1;
            row.querySelector('.eab-section-number').textContent = index + 1;
            row.querySelectorAll('input').forEach(field => { field.disabled = recurring; });
            for (const edge of ['start', 'end']) {
                const noTime = row.querySelector(`.psource_event_no_${edge}_time`);
                const time = row.querySelector(`.psource_event_${edge}_time`);
                time.hidden = noTime.checked;
                time.disabled = recurring || noTime.checked;
            }
        });
    }

    if (toggle) {
        toggle.setAttribute('aria-controls', repeat.id);
        toggle.setAttribute('aria-expanded', 'false');
        toggle.addEventListener('click', () => {
            recurring = !recurring;
            [toggle.value, toggle.dataset.eabAlter_label] = [toggle.dataset.eabAlter_label, toggle.value];
            toggle.setAttribute('aria-expanded', String(recurring));
            sync();
        });
    }

    if (rows) {
        const template = document.getElementById('eab-add-more-bank');
        const addButton = document.getElementById('eab-add-more-button');
        addButton.addEventListener('click', () => {
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('[id], [name], [for]').forEach(field => {
                for (const attr of ['id', 'name', 'for']) {
                    if (field.hasAttribute(attr)) {
                        field.setAttribute(attr, field.getAttribute(attr).replace('bank', nextIndex));
                    }
                }
            });
            const start = fragment.querySelector('.psource_event_start');
            nextIndex++;
            rows.append(fragment);
            sync();
            start.focus();
        });
        rows.addEventListener('click', event => {
            const remove = event.target.closest('.eab-event-remove_time');
            if (!remove) return;
            event.preventDefault();
            if (dateRows().length <= 1) return;
            remove.closest('.eab-section-block').remove();
            sync();
            addButton.focus();
        });
        rows.addEventListener('change', sync);
    }
    if (interval) interval.addEventListener('change', sync);

    const message = document.createElement('p');
    message.setAttribute('role', 'alert');
    message.hidden = true;
    document.getElementById('psource_event_times_label').after(message);
    form.addEventListener('input', () => { message.hidden = true; });
    form.addEventListener('submit', event => {
        if (event.submitter?.id === 'save-post' || document.getElementById('icl_translation_of')) return;
        let invalid = null;
        if (recurring) {
            const start = document.getElementById('eab_event-repeat_start');
            const end = document.getElementById('eab_event-repeat_end');
            invalid = !start.value ? start : !interval.value ? interval : !end.value || end.value < start.value ? end : null;
        } else if (rows) {
            for (const row of dateRows()) {
                const start = row.querySelector('.psource_event_start');
                const end = row.querySelector('.psource_event_end');
                const startTime = row.querySelector('.psource_event_start_time');
                const endTime = row.querySelector('.psource_event_end_time');
                const from = startTime.disabled ? '00:01' : startTime.value;
                const to = endTime.disabled ? '23:59' : endTime.value;
                if (!start.value) invalid = start;
                else if (!from) invalid = startTime;
                else if (!end.value) invalid = end;
                else if (!to) invalid = endTime;
                else if (`${start.value}T${from}` >= `${end.value}T${to}`) invalid = end;
                if (invalid) break;
            }
        }
        if (invalid) {
            event.preventDefault();
            event.stopImmediatePropagation();
            message.textContent = eabEventDates.invalidRange;
            message.hidden = false;
            invalid.focus();
        }
    }, true);
    sync();
})();
