document.addEventListener("DOMContentLoaded", function() {
    const reservationForm = document.getElementById('reservation-form');
    if (!reservationForm) {
        return;
    }

    // This data is localized from the PHP side
    const disponibilites = window.ReservationsData && window.ReservationsData.disponibilites ? window.ReservationsData.disponibilites : {};
    const selectHeure = document.getElementById('heure');

    function updateHeures() {
        const selectedDateRadio = document.querySelector("input[name='date']:checked");
        if (!selectedDateRadio) {
            // Clear the time slot options if no date is selected
            selectHeure.innerHTML = '<option value=""><?php echo esc_js(__("Veuillez d\'abord choisir une date", "reservations-personnalise")); ?></option>';
            return;
        }

        const selectedDate = selectedDateRadio.value;
        const heures = disponibilites[selectedDate] || [];
        selectHeure.innerHTML = '';

        if (heures.length === 0) {
            const opt = document.createElement('option');
            opt.textContent = '<?php echo esc_js(__("Aucun créneau disponible", "reservations-personnalise")); ?>';
            opt.disabled = true;
            selectHeure.appendChild(opt);
        } else {
            heures.forEach(function(h) {
                const o = document.createElement('option');
                o.value = h;
                o.textContent = h;
                selectHeure.appendChild(o);
            });
        }
    }

    document.querySelectorAll("input[name='date']").forEach(function(radio) {
        radio.addEventListener('change', updateHeures);
    });

    // Initial population of time slots based on the default selected date
    updateHeures();
});