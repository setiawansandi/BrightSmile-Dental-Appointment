<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Doctors</title>
  <link rel="stylesheet" href="css/doctors.css" />
  <link rel="stylesheet" href="css/root.css" />
  <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet" />
</head>

<body>
  <?php require_once __DIR__ . '/components/navbar.php'; ?>

  <!-- Hero Section -->
  <section class="hero">
    <h1>Meet Our Expert Team</h1>
    <p>Our highly skilled dental professionals are committed to providing<br>exceptional care with the latest techniques
    </p>
  </section>

  <!-- Doctors Section -->
  <section class="team">
    <div class="card" data-name="Dr Nicholas Bidasso" data-specialty="General Dentistry"
      data-description="Dr Nicholas Bidasso graduated from the University of Melbourne, Australia, with a Bachelor of Dental Surgery. He returned to Singapore and served his bond in the public sector, gaining broad experience at various polyclinics and the Health Promotion Board. This period provided him with a strong foundation in community dental care, preventive dentistry, and managing patients of all ages. After his public service, he transitioned to private practice before joining SmileFocus Dental. Dr Bidasso is a firm believer in patient-first dentistry, emphasizing clear communication and gentle care to help anxious patients feel comfortable.">
      <img src="assets\images\doc1.png" alt="Doctor">
      <h3>Dr Nicholas Bidasso</h3>
      <p>General Dentistry</p>
      <div class="actions">
        <button class="btn-base btn-info">Info</button>
        <a href="appointment.html" class="btn-base btn-book">Book</a>
      </div>
    </div>

    <div class="card" data-name="Dr Isabelle Woo" data-specialty="Cosmetic Dentistry"
      data-description="Dr Isabelle Woo graduated from King's College London, United Kingdom. Following her graduation, she practiced in London, where she developed a strong interest in aesthetic dentistry, prompting her to complete numerous advanced courses in smile design and ceramic veneers. Upon returning to Singapore, she worked exclusively in private practices focused on aesthetic transformations. Dr Woo is skilled in a range of cosmetic procedures, including veneers, teeth whitening, and full smile makeovers. She believes in combining the art and science of dentistry to create beautiful, natural-looking smiles that are unique to each patient.">
      <img src="assets\images\doc2.png" alt="Doctor">
      <h3>Dr Isabelle Woo</h3>
      <p>Cosmetic Dentistry</p>
      <div class="actions">
        <button class="btn-base btn-info">Info</button>
        <a href="appointment.html" class="btn-base btn-book">Book</a>
      </div>
    </div>

    <div class="card" data-name="Dr Zhang Jing" data-specialty="Orthodontics"
      data-description="Dr Zhang Jing received his Bachelor of Dental Surgery from the National University of Singapore (NUS). After a few years in general practice, he pursued his specialist training and obtained a Master of Dental Surgery in Orthodontics from NUS. He is a registered specialist with the Singapore Dental Council and practiced at the National Dental Centre Singapore, handling complex braces and aligner cases. Dr. Zhang is passionate about the functional and aesthetic benefits of a well-aligned bite. He is dedicated to using modern digital technologies to create precise, effective treatment plans for both children and adults.">
      <img src="assets\images\doc3.png" alt="Doctor">
      <h3>Dr Zhang Jing</h3>
      <p>Orthodontics</p>
      <div class="actions">
        <button class="btn-base btn-info">Info</button>
        <a href="appointment.html" class="btn-base btn-book">Book</a>
      </div>
    </div>

    <div class="card" data-name="Dr Amanda See" data-specialty="General Dentistry"
      data-description="Dr Amanda See graduated from the University of Southampton, United Kingdom. She returned to Singapore upon graduation and practised in several hospitals including Singapore General Hospital, National University Hospital and Changi General Hospital, where she has gained experience in multiple specialities such as General Medicine, General Surgery, and General Practice. She joined Raffles Medical Group for three years before joining BrightSmile. Dr See is particularly passionate about promoting women’s health in Singapore. She strives to promote awareness of women’s health through patient education, health screening and modification of lifestyle.">
      <img src="assets\images\doc4.jpg" alt="Doctor">
      <h3>Dr Amanda See</h3>
      <p>General Dentistry</p>
      <div class="actions">
        <button class="btn-base btn-info">Info</button>
        <a href="appointment.html" class="btn-base btn-book">Book</a>
      </div>
    </div>
  </section>

  <!-- Modal -->
  <div id="doctorModal" class="modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2>Doctor Info</h2>

      <div class="modal-body">

        <div class="modal-left">
          <img id="modalImage" src="" alt="Doctor">
          <h3 id="modalName"></h3>
          <p id="modalSpecialty"></p>
        </div>

        <div class="modal-right">
          <p id="modalDescription"></p>
          <a href="appointment.html" class="btn-base btn-book">Book</a>
        </div>

      </div>
    </div>
  </div>


  <?php require __DIR__ . '/components/footer.php'; ?>
  <script src="js\doctors.js"></script>
</body>

</html>