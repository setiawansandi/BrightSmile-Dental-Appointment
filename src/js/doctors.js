// Get modal elements
const modal = document.getElementById("doctorModal");
const modalImg = document.getElementById("modalImage");
const modalName = document.getElementById("modalName");
const modalSpecialty = document.getElementById("modalSpecialty");
const modalDescription = document.getElementById("modalDescription");
const closeBtn = document.querySelector(".close");

// Open modal when clicking "More Info"
document.querySelectorAll(".btn-info").forEach(button => {
  button.addEventListener("click", (e) => {
    const card = e.target.closest(".card");
    modal.style.display = "block";
    modalName.textContent = card.dataset.name;
    modalSpecialty.textContent = card.dataset.specialty;
    modalDescription.textContent = card.dataset.description;
    modalImg.src = card.querySelector("img").src;
  });
});

// Close modal
closeBtn.onclick = function() {
  modal.style.display = "none";
};

// Close when clicking outside modal
window.onclick = function(event) {
  if (event.target == modal) {
    modal.style.display = "none";
  }
};
