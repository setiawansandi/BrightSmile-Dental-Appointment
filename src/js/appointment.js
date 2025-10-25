document.addEventListener("DOMContentLoaded", function () {
  // --- CACHE ALL RELEVANT ELEMENTS ---
  // Timeslot elements
  const timeslotButtons = document.querySelectorAll(".timeslot-btn");
  const selectedTimeInput = document.getElementById("selected_timeslot");

  // Doctor selector elements
  const doctorSelectorCard = document.querySelector(".doctor-selector");
  const dropdownMock = document.querySelector(".dropdown-mock");
  const dropdownContent = document.querySelector(".dropdown-content-wrapper");
  const doctorList = document.querySelector(".doctor-list");
  const doctorItems = document.querySelectorAll(".doctor-list .doctor-item");
  const selectedDoctorInput = document.getElementById("selected_doctor_id");

  // Date input element
  const apptDateInput = document.getElementById("appt_date_input");

  // --- NEW: FUNCTION TO FETCH AVAILABILITY ---
  async function fetchAvailability() {
    // 1. Get current values
    const doctorId = selectedDoctorInput.value;
    const date = apptDateInput.value;

    // 2. Don't do anything if we don't have both values
    if (!doctorId || !date) {
      return;
    }

    // 3. Reset all buttons (remove 'disabled')
    timeslotButtons.forEach((btn) => {
      btn.classList.remove("disabled");
    });

    try {
      // 4. Fetch the list of booked times
      const response = await fetch(
        `appointment.php?doctor=${doctorId}&date=${date}`
      );
      if (!response.ok) {
        throw new Error("Network response was not ok");
      }
      const bookedTimes = await response.json();

      // 5. If we got an array, disable the matching buttons
      if (Array.isArray(bookedTimes)) {
        timeslotButtons.forEach((btn) => {
          if (bookedTimes.includes(btn.innerText)) {
            btn.classList.add("disabled");
            // If the currently selected time is now disabled, deselect it
            if (btn.classList.contains("selected")) {
              btn.classList.remove("selected");
              selectedTimeInput.value = "";
            }
          }
        });
      }
    } catch (error) {
      console.error("Error fetching availability:", error);
      // You could show an error to the user here
    }
  }

  // --- TIMESLOT BUTTON LOGIC ---
  timeslotButtons.forEach((button) => {
    button.addEventListener("click", function () {
      if (this.classList.contains("disabled")) {
        return; // Do nothing if disabled
      }
      timeslotButtons.forEach((btn) => {
        btn.classList.remove("selected");
      });
      this.classList.add("selected");
      if (selectedTimeInput) {
        selectedTimeInput.value = this.innerText;
      }
    });
  });

  // --- DOCTOR DROPDOWN LOGIC ---
  if (dropdownMock) {
    dropdownMock.addEventListener("click", () => {
      doctorSelectorCard.classList.toggle("open");
    });
  }

  doctorItems.forEach((item) => {
    item.addEventListener("click", () => {
      if (dropdownContent) {
        dropdownContent.innerHTML = item.outerHTML;
      }
      doctorSelectorCard.classList.add("has-selection");
      doctorSelectorCard.classList.remove("open");

      if (selectedDoctorInput) {
        selectedDoctorInput.value = item.dataset.doctorId;
      }

      // --- NEW: Trigger availability check ---
      fetchAvailability();
    });
  });

  // --- NEW: DATE INPUT LISTENER ---
  if (apptDateInput) {
    apptDateInput.addEventListener("change", () => {
      // --- NEW: Trigger availability check ---
      fetchAvailability();
    });
  }

  // Optional: Close dropdown if clicking outside
  window.addEventListener("click", function (e) {
    if (doctorSelectorCard && !doctorSelectorCard.contains(e.target)) {
      doctorSelectorCard.classList.remove("open");
    }
  });

  // --- NEW: Initial check on page load ---
  // This handles the pre-filled form in "reschedule" mode.
  if (apptDateInput && selectedDoctorInput) {
    fetchAvailability();
  }
});