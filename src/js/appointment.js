document.addEventListener('DOMContentLoaded', function() {
  const doctorInput = document.getElementById('selected_doctor_id'); // hidden input
  const dateInput = document.getElementById('appt_date_input');
  const timeslotBtns = () => Array.from(document.querySelectorAll('.timeslot-btn'));
  const hiddenTimeslotInput = document.getElementById('selected_timeslot');

  function refreshTimeslots() {
    const doctorId = doctorInput.value;
    const date = dateInput.value;
    if (!doctorId || !date) return;

    fetch(`appointment.php?doctor=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(date)}`)
      .then(r => r.json())
      .then(booked => {
        // normalize booked into map: time -> {is_mine, appointment_id}
        const map = {};
        if (Array.isArray(booked)) {
          booked.forEach(b => { map[b.time] = b; });
        }

        timeslotBtns().forEach(btn => {
          const slot = btn.dataset.time || btn.textContent.trim();
          btn.classList.remove('booked','your-booking','selected');
          btn.disabled = false;

          const info = map[slot];
          if (info) {
            if (info.is_mine) {
              // your booking — highlight but allow selection (for reschedule)
              btn.classList.add('your-booking','selected');
              // make sure hidden input is set so form will submit this time by default
              if (hiddenTimeslotInput) hiddenTimeslotInput.value = slot;
            } else {
              // someone else booked it — mark disabled / unavailable
              btn.classList.add('booked');
              btn.disabled = true;
            }
          }
        });
      })
      .catch(err => {
        console.error('Failed to fetch booked slots', err);
      });
  }

  // hook up date change and doctor selection clicks
  if (dateInput) dateInput.addEventListener('change', refreshTimeslots);

  // clicking timeslot: set selected value (but don't allow clicking disabled ones)
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('.timeslot-btn');
    if (!btn) return;
    if (btn.disabled) return;
    // deselect others
    timeslotBtns().forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    if (hiddenTimeslotInput) hiddenTimeslotInput.value = btn.dataset.time || btn.textContent.trim();
  });

  // initial refresh on load (if date is pre-filled)
  refreshTimeslots();
});
