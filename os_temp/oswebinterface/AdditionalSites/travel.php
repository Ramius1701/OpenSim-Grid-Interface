<?php
require_once 'include/config.php';
$baseadress = SITE_NAME;

// Bilder aus dem Verzeichnis 'images' laden
$imageDir = 'images/';
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
$images = [];

if (is_dir($imageDir)) {
    $files = scandir($imageDir);
    foreach ($files as $file) {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($extension, $allowedExtensions)) {
            $images[] = $imageDir . $file;
        }
    }
}

// 5 zufällige Bilder auswählen
$randomImages = [];
if (count($images) > 0) {
    $randomKeys = array_rand($images, min(5, count($images)));
    if (is_array($randomKeys)) {
        foreach ($randomKeys as $key) {
            $randomImages[] = $images[$key];
        }
    } else {
        $randomImages[] = $images[$randomKeys];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title><?php echo $baseadress; ?> Travel</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<style>
body,h1,h2,h3,h4,h5,h6 {font-family: "Raleway", Arial, Helvetica, sans-serif}
.myLink {display: none}
.gridsearch-container {
  position: absolute;
  left: 5%;
  top: 50px;
  transform: translateX(-80%);
  width: 30%;
  background: rgba(255, 255, 255, 0.9);
  padding: 16px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}
</style>
</head>
<body class="w3-light-grey">

<!-- Navigation Bar -->
<div class="w3-bar w3-white w3-border-bottom w3-xlarge">
  <a href="#" class="w3-bar-item w3-button w3-text-blue w3-hover-blue"><b><i class="fa fa-desktop w3-margin-right"></i><?php echo $baseadress; ?> Travel</b></a>
  <a href="#" class="w3-bar-item w3-button w3-right w3-hover-blue w3-text-grey"><i class="fa fa-search"></i></a>
</div>

<!-- Header -->
<header class="w3-display-container w3-content w3-hide-small" style="max-width:1500px">
  <div class="gridsearch-container">
    <div class="w3-bar w3-black">
      <button class="w3-bar-item w3-button tablink" onclick="openLink(event, 'Gridsuche');"><i class="fa fa-search w3-margin-right"></i>Gridsuche</button>
      <button class="w3-bar-item w3-button tablink" onclick="openLink(event, 'Network');"><i class="fa fa-globe w3-margin-right"></i>Network</button>
      <button class="w3-bar-item w3-button tablink" onclick="openLink(event, 'Storage');"><i class="fa fa-hdd-o w3-margin-right"></i>Storage</button>
    </div>

    <!-- Tabs -->
    <div id="Gridsuche" class="w3-container w3-leftalign w3-white w3-padding-16 myLink">
      <h3>Gridsuche</h3>
      <div class="w3-row-padding" style="margin:0 -16px;">
        <div class="w3-half">
          <label>Gridname</label>
          <input class="w3-input w3-border" type="text" placeholder="Gridname" id="gridname" oninput="searchGrid()">
        </div>
        <div class="w3-half">
          <label>Gridlink</label>
          <input class="w3-input w3-border" type="text" placeholder="Gridlink" id="gridlink" oninput="searchGrid()">
        </div>
      </div>
    </div>

    <div id="Network" class="w3-container w3-white w3-padding-16 myLink">
      <h3>Network Settings</h3>
      <p>Configure your network settings here.</p>
      <p><button class="w3-button w3-dark-grey">Configure Network</button></p>
    </div>

    <div id="Storage" class="w3-container w3-white w3-padding-16 myLink">
      <h3>Storage Management</h3>
      <p>Manage your storage devices and partitions.</p>
      <p><button class="w3-button w3-dark-grey">Manage Storage</button></p>
    </div>
  </div>
</header>

<!-- Page content -->
<div class="w3-content" style="max-width:1100px;">

  <!-- Empfohlene Grids -->
  <div class="w3-container w3-margin-top">
    <h3>Grids</h3>
    <h6>Eine Auswahl von Grids:</h6>
    <div class="w3-responsive">
      <table class="w3-table w3-striped w3-bordered">
        <thead>
          <tr class="w3-blue">
            <th>Gridname</th>
            <th>Gridlink</th>
          </tr>
        </thead>
        <tbody id="gridlist">
          <!-- Gridliste wird hier dynamisch eingefügt -->
        </tbody>
      </table>
    </div>
  </div>

  <!-- Zufällige Bilder -->
  <div class="w3-container">
    <h3>Bilder</h3>
    <div class="w3-row-padding">
      <div id="randomImages" class="w3-row-padding">
        <?php
        if (count($randomImages) > 0) {
            foreach ($randomImages as $image) {
                echo '<div class="w3-half w3-margin-bottom">';
                echo '<img src="' . $image . '" alt="Random Image" style="width:100%">';
                echo '</div>';
            }
        } else {
            echo '<p>Keine Bilder gefunden.</p>';
        }
        ?>
      </div>
    </div>
  </div>

  <!-- Kalender -->
  <div class="w3-container">
    <h3>Nächste Termine</h3>
    <div id="calendarEvents">
      <!-- Kalendertermine werden hier dynamisch eingefügt -->
    </div>
  </div>

  <!-- TOS and DMCA -->
  <div class="w3-container">
    <h3>Legal</h3>
    <p><a href="include/tos.php">Terms of Service</a> | <a href="include/dmca.php">DMCA</a></p>
  </div>
  
<!-- End page content -->
</div>

<!-- Footer -->
<footer class="w3-container w3-center w3-opacity w3-margin-bottom">
  <h5>Find Us On</h5>
  <div class="w3-xlarge w3-padding-16">
    <i class="fa fa-facebook-official w3-hover-opacity"></i>
    <i class="fa fa-instagram w3-hover-opacity"></i>
    <i class="fa fa-snapchat w3-hover-opacity"></i>
    <i class="fa fa-pinterest-p w3-hover-opacity"></i>
    <i class="fa fa-twitter w3-hover-opacity"></i>
    <i class="fa fa-linkedin w3-hover-opacity"></i>
  </div>
  <p>Powered by <a href="https://www.w3schools.com/w3css/default.asp" target="_blank" class="w3-hover-text-green">w3.css</a></p>
</footer>

<script>
// Tabs
function openLink(evt, linkName) {
  var i, x, tablinks;
  x = document.getElementsByClassName("myLink");
  for (i = 0; i < x.length; i++) {
    x[i].style.display = "none";
  }
  tablinks = document.getElementsByClassName("tablink");
  for (i = 0; i < x.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" w3-blue", "");
  }
  document.getElementById(linkName).style.display = "block";
  evt.currentTarget.className += " w3-blue";
}

// Zufällige Auswahl von Grids
function getRandomGrids(grids, count) {
  const shuffled = grids.sort(() => 0.5 - Math.random());
  return shuffled.slice(0, count);
}

// Gridliste laden
function loadGridList() {
  fetch('include/gridlist.csv')
    .then(response => response.text())
    .then(data => {
      const rows = data.split('\n').filter(row => row.trim() !== '');
      const randomGrids = getRandomGrids(rows, 5); // 5 zufällige Grids auswählen
      const gridlist = document.getElementById('gridlist');
      gridlist.innerHTML = ''; // Vorhandene Einträge löschen

      randomGrids.forEach(row => {
        const [gridname, gridlink] = row.split(',');
        if (gridname && gridlink) {
          const tr = document.createElement('tr');
          tr.innerHTML = `<td>${gridname}</td><td>${gridlink}</td>`;
          gridlist.appendChild(tr);
        }
      });
    })
    .catch(error => console.error('Fehler beim Laden der Gridliste:', error));
}

// Gridsuche
function searchGrid() {
  const gridname = document.getElementById('gridname').value.toLowerCase();
  const gridlink = document.getElementById('gridlink').value.toLowerCase();
  const rows = document.querySelectorAll('#gridlist tr');

  rows.forEach(row => {
    const name = row.cells[0].textContent.toLowerCase();
    const link = row.cells[1].textContent.toLowerCase();
    if (name.includes(gridname) && link.includes(gridlink)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

// Kalendertermine laden
function loadCalendarEvents() {
  fetch('calendar/events.json')
    .then(response => response.json())
    .then(events => {
      const now = new Date();
      const upcomingEvents = events
        .filter(event => new Date(event.date) >= now) // Nur zukünftige Termine
        .sort((a, b) => new Date(a.date) - new Date(b.date)) // Nach Datum sortieren
        .slice(0, 5); // Nur die nächsten 5 Termine

      const calendarContainer = document.getElementById('calendarEvents');
      calendarContainer.innerHTML = ''; // Vorhandene Termine löschen

      upcomingEvents.forEach(event => {
        const div = document.createElement('div');
        div.className = 'w3-panel w3-border';
        div.style.backgroundColor = event.color;
        div.style.color = event.txtcolor;
        div.innerHTML = `
          <img src="${event.image}" alt="Event Image" style="width:100%">
          <h4>${event.texts[0]}</h4>
          <p>${event.texts[1]}</p>
          <p>${event.texts[2]}</p>
          <p>${event.texts[3]}</p>
          <p>${event.texts[4]}</p>
          <a href="${event.link}">More Info</a>
        `;
        calendarContainer.appendChild(div);
      });
    })
    .catch(error => console.error('Fehler beim Laden der Kalendertermine:', error));
}

// Beim Laden der Seite die Inhalte laden
document.addEventListener('DOMContentLoaded', () => {
  loadGridList();
  loadCalendarEvents();
  document.getElementsByClassName("tablink")[0].click();
});
</script>

</body>
</html>