// Wait for the HTML document to be fully loaded before running the script
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Find all buttons with the 'timeslot-btn' class
    const timeslotButtons = document.querySelectorAll(".timeslot-btn");
    
    // --- NEW: Find the hidden input for time ---
    const selectedTimeInput = document.getElementById("selected_timeslot");

    // 2. Loop through each button and add a click event listener
    timeslotButtons.forEach(button => {
        button.addEventListener("click", function() {
            
            // 3. Check if the clicked button is disabled
            if (this.classList.contains("disabled")) {
                return;
            }

            // 4. If it's not disabled, first remove the 'selected' 
            //    class from ALL timeslot buttons
            timeslotButtons.forEach(btn => {
                btn.classList.remove("selected");
            });

            // 5. Finally, add the 'selected' class ONLY 
            //    to the button that was just clicked
            this.classList.add("selected");

            // --- NEW: 6. Get the time and update the hidden input ---
            if(selectedTimeInput) {
                selectedTimeInput.value = this.innerText; // e.g., "09:00"
            }
        });
    });

    // --- Doctor Dropdown Code ---

    // 1. Get all the necessary elements
    const doctorSelectorCard = document.querySelector(".doctor-selector");
    const dropdownMock = document.querySelector(".dropdown-mock");
    const dropdownContent = document.querySelector(".dropdown-content-wrapper");
    const doctorList = document.querySelector(".doctor-list");
    // Get items *only* from the list, not from the content wrapper
    const doctorItems = document.querySelectorAll(".doctor-list .doctor-item"); 

    // --- NEW: Find the hidden input for doctor ID ---
    const selectedDoctorInput = document.getElementById("selected_doctor_id");

    // 2. Add click event to the main dropdown box to toggle it
    if (dropdownMock) {
        dropdownMock.addEventListener("click", () => {
            // Only toggle if it doesn't have a selection, or to close it
            doctorSelectorCard.classList.toggle("open");
        });
    }

    // 3. Add click events to each *item* in the list
    doctorItems.forEach(item => {
        item.addEventListener("click", () => {
            
            // A. Copy the *entire* .doctor-item element
            if (dropdownContent) {
                dropdownContent.innerHTML = item.outerHTML;
            }
            
            // B. Add a 'has-selection' class to the parent card
            doctorSelectorCard.classList.add("has-selection");

            // C. Close the dropdown list
            doctorSelectorCard.classList.remove("open");

            // --- NEW: D. Get doctor ID from data-attribute and update hidden input ---
            if (selectedDoctorInput) {
                // 'dataset.doctorId' reads the 'data-doctor-id' attribute
                selectedDoctorInput.value = item.dataset.doctorId;
            }
        });
    });

    // Optional: Close dropdown if clicking outside of it
    window.addEventListener('click', function(e) {
        if (doctorSelectorCard && !doctorSelectorCard.contains(e.target)) {
            doctorSelectorCard.classList.remove('open');
        }
    });

});