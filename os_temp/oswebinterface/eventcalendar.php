<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Calendar</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; padding: 20px; }
        .calendar { width: 100%; max-width: 1150px; border: 1px solid #ccc; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; background: #007bff; color: #fff; padding: 10px; }
        .days { display: grid; grid-template-columns: repeat(7, 1fr); background: #f0f0f0; }
        .day { text-align: center; padding: 8px 0; font-weight: bold; }
        .dates { display: grid; grid-template-columns: repeat(7, 1fr); }
        .date { text-align: left; padding: 10px; border: 1px solid #ccc; position: relative; aspect-ratio: 1 / 1; background-size: cover; background-position: center; display: flex; flex-direction: column; font-size: 0.7em; }
        .date-number { align-self: flex-start; font-size: 1.4em; z-index: 1; }
        .event { font-size: 0.8em; background-color: rgba(255, 255, 255, 0.7); padding: 2px 4px; border-radius: 3px; margin-top: auto; text-align: center; z-index: 1; }
        .event-text { margin: 2px 0; z-index: 1; }
        .event-text-bold { font-weight: bold; }
        .image-container { position: absolute; top: 0; left: 0; width: 100%; height: 100%; overflow: hidden; }
        .image-container img { width: 100%; height: 100%; object-fit: cover; }
        .event-link { font-weight: bold; text-align: center; display: block; margin-top: auto; }
    </style>
</head>
<body>
    <div class="calendar">
        <div class="header">
            <button onclick="changeMonth(-1)">&lt;</button>
            <h2 id="month-year"></h2>
            <button onclick="changeMonth(1)">&gt;</button>
        </div>
        <div class="days">
            <div class="day">Mo</div>
            <div class="day">Di</div>
            <div class="day">Mi</div>
            <div class="day">Do</div>
            <div class="day">Fr</div>
            <div class="day">Sa</div>
            <div class="day">So</div>
        </div>
        <div id="dates" class="dates"></div>
    </div>

    <script>
        const monthYear = document.getElementById('month-year');
        const dates = document.getElementById('dates');
        let currentDate = new Date();

        async function fetchEvents() {
            const response = await fetch('calendar/events.json');
            const events = await response.json();
            return events;
        }

        async function renderCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const startDay = (firstDay === 0) ? 6 : firstDay - 1; // Montag als erster Tag

            monthYear.textContent = currentDate.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' });
            dates.innerHTML = '';

            const events = await fetchEvents();

            for (let i = 0; i < startDay; i++) {
                const emptyCell = document.createElement('div');
                dates.appendChild(emptyCell);
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const dateCell = document.createElement('div');
                dateCell.classList.add('date');
                
                const dateNumber = document.createElement('div');
                dateNumber.classList.add('date-number');
                dateNumber.textContent = day;
                dateCell.appendChild(dateNumber);

                const event = events.find(e => new Date(e.date).toDateString() === new Date(year, month, day).toDateString());
                if (event) {
                    if (event.image) {
                        const imageContainer = document.createElement('div');
                        imageContainer.classList.add('image-container');
                        const img = document.createElement('img');
                        img.src = event.image;
                        imageContainer.appendChild(img);
                        dateCell.appendChild(imageContainer);
                    }

                    if (event.color) {
                        dateCell.style.backgroundColor = event.color;
                    }

                    if (event.txtcolor) {
                        dateCell.style.color = event.txtcolor;
                    }

                    event.texts.forEach((text, index) => {
                        const eventTextElement = document.createElement('div');
                        eventTextElement.classList.add('event-text');
                        if (index === 0) {
                            eventTextElement.classList.add('event-text-bold');
                        }
                        eventTextElement.textContent = text;
                        dateCell.appendChild(eventTextElement);
                    });

                    const eventElement = document.createElement('div');
                    eventElement.classList.add('event');
                    const lastWord = event.link.split('/').pop();
                    eventElement.innerHTML = `<a href="${event.link}" target="_blank" class="event-link">${lastWord}</a>`;
                    dateCell.appendChild(eventElement);
                }

                dates.appendChild(dateCell);
            }
        }

        function changeMonth(offset) {
            currentDate.setMonth(currentDate.getMonth() + offset);
            renderCalendar();
        }

        renderCalendar();
    </script>
</body>
</html>
