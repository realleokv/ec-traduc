const monthNames = [
    "Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho",
    "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"
];

export function initCalendar() {
    const slotsData = window.dbHorariosLivres || [];
    const slotsByDate = {};

    slotsData.forEach(slot => {
        const dataStr = slot.data_inicio || slot.data;
        if (!dataStr) return;

        const partes = dataStr.split(' ');
        const datePart = partes[0];
        const timePart = partes[1] ? partes[1].substring(0, 5) : '';

        const endStr = slot.data_fim || slot.hora_fim || '';
        const endPart = endStr.includes(' ') ? endStr.split(' ')[1].substring(0, 5) : endStr.substring(0, 5);

        if (!slotsByDate[datePart]) slotsByDate[datePart] = [];

        slotsByDate[datePart].push({
            id: slot.id,
            inicio: timePart,
            fim: endPart,
            fullDate: datePart
        });
    });

    window.slotsByDate = slotsByDate;

    let viewDate = new Date();
    const datasComHorario = Object.keys(slotsByDate);

    if (datasComHorario.length > 0) {
        const [ano, mes, dia] = datasComHorario[0].split('-');
        viewDate = new Date(parseInt(ano), parseInt(mes) - 1, parseInt(dia));
    }

    const monthYearEl = document.getElementById('cal-month-year');
    const daysContainer = document.getElementById('calendar-days');
    const prevBtn = document.getElementById('cal-prev');
    const nextBtn = document.getElementById('cal-next');
    const slotsContainer = document.getElementById('slots-container');
    const slotsList = document.getElementById('slots-list');
    const selectedDateText = document.getElementById('selected-date-text');

    // Elementos do Modal
    const modal = document.getElementById('modal-agendamento');
    const closeBtn = document.getElementById('btn-close-modal');

    function openModal(slot) {
        if (!modal) return;

        const inputData = document.getElementById('form-data');
        const inputInicio = document.getElementById('form-hora-inicio');
        const inputFim = document.getElementById('form-hora-fim');
        const slotBadge = document.getElementById('modal-slot-info');

        if (inputData) inputData.value = slot.fullDate;
        if (inputInicio) inputInicio.value = slot.inicio;
        if (inputFim) inputFim.value = slot.fim;

        const [y, m, d] = slot.fullDate.split('-');
        if (slotBadge) {
            slotBadge.textContent = `📅 ${d}/${m}/${y} — ⏰ ${slot.inicio} às ${slot.fim}`;
        }

        modal.classList.add('is-active');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('is-active');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);

    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('is-active')) {
                closeModal();
            }
        });
    }

    if (!daysContainer || !monthYearEl) return;

    function renderCalendar() {
        const year = viewDate.getFullYear();
        const month = viewDate.getMonth();

        monthYearEl.textContent = `${monthNames[month]} ${year}`;
        daysContainer.innerHTML = '';

        const firstDayIndex = new Date(year, month, 1).getDay();
        const lastDay = new Date(year, month + 1, 0).getDate();

        for (let i = 0; i < firstDayIndex; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'cal-day empty';
            daysContainer.appendChild(emptyCell);
        }

        for (let day = 1; day <= lastDay; day++) {
            const dayCell = document.createElement('div');
            dayCell.className = 'cal-day';
            dayCell.textContent = day;

            const monthFormatted = String(month + 1).padStart(2, '0');
            const dayFormatted = String(day).padStart(2, '0');
            const dateKey = `${year}-${monthFormatted}-${dayFormatted}`;

            if (slotsByDate[dateKey] && slotsByDate[dateKey].length > 0) {
                dayCell.classList.add('has-free-slots');
                dayCell.setAttribute('role', 'button');
                dayCell.setAttribute('tabindex', '0');
                dayCell.setAttribute('aria-label', `Ver horários livres em ${dayFormatted}/${monthFormatted}/${year}`);

                const select = () => {
                    document.querySelectorAll('.cal-day').forEach(d => d.classList.remove('selected'));
                    dayCell.classList.add('selected');
                    showSlotsForDate(dateKey);
                };

                dayCell.addEventListener('click', select);
                dayCell.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        select();
                    }
                });
            }

            daysContainer.appendChild(dayCell);
        }
    }

    function showSlotsForDate(dateKey) {
        if (!slotsContainer || !slotsList) return;

        const slots = slotsByDate[dateKey] || [];
        const [y, m, d] = dateKey.split('-');

        if (selectedDateText) {
            selectedDateText.textContent = `Horários livres para ${d}/${m}/${y}:`;
        }

        slotsList.innerHTML = '';

        slots.forEach(slot => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'slot-btn';
            btn.textContent = `${slot.inicio} - ${slot.fim}`;

            btn.addEventListener('click', () => {
                openModal(slot);
            });

            slotsList.appendChild(btn);
        });

        slotsContainer.style.display = 'block';
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            viewDate.setMonth(viewDate.getMonth() - 1);
            if (slotsContainer) slotsContainer.style.display = 'none';
            renderCalendar();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            viewDate.setMonth(viewDate.getMonth() + 1);
            if (slotsContainer) slotsContainer.style.display = 'none';
            renderCalendar();
        });
    }

    renderCalendar();
}