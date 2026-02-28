$(document).ready(function()
{
    var date = new Date();
    var d = date.getDate();
    var m = date.getMonth();
    var y = date.getFullYear();
    var url = "../osmeetinglog/";
        
    $('#calendar').fullCalendar({
        header: {
            left: 'prev,next today',
            center: 'title',
            right: 'month,agendaWeek,agendaDay'
        },
        defaultDate: new Date(y, m, d),
        editable: true,
        eventLimit: true,
        events: [
            /*
            {
                title: 'All Day Event',
                start: new Date(y, m, 1)
            },
            {
                title: 'Long Event',
                start: new Date(y, m, d-5),
                end: new Date(y, m, d-2)
            },
            */
            {
                id: 999,
                title: 'Meeting',
                /*start: new Date(y, m, d+1, 20, 21),*/
                start: new Date(y, m, 1, 21, 00),
                url: url,
                allDay: false
            },
            {
                id: 999,
                title: 'Meeting',
                start: new Date(y, m, d+1, 20, 00),
                /*start: new Date(y, m, 8, 21, 24),*/
                url: url,
                allDay: false
            },
            {
                title: 'April Fools Day \r\n (Earth)',
                start: new Date(y, m+2, 1),
                url: url,
                allDay: true
            },
            {
                title: 'Day of Honour \r\n (Klingon)',
                start: new Date(y, m+2, 5),
                url: url,
                allDay: true
            },
            {
                title: 'First Conact Day \r\n (Federation)',
                start: new Date(y, m+2, 5),
                url: url,
                allDay: true
            },
            {
                title: 'Frontier Day\r\n (Federation)',
                start: new Date(y, m+2, 13),
                url: url,
                allDay: true
            },
            {
                title: 'Ancestors Eve \r\n (Voyager)',
                start: new Date(y, m+2, 22),
                url: url,
                allDay: true
            },
            {
                title: 'Captain Picard Day \r\n (The Next Generation)',
                start: new Date(y, m+4, 16),
                url: url,
                allDay: true
            },
            {
                title: 'The Lohlunat \r\n "Festival of the Moons" \r\n (Risa)',
                start: new Date(y, m+4, 28),
                end: new Date(y, m+5, 29),
                url: url,
                allDay: true
            },
            {
                title: 'Federation Day',
                start: new Date(y, m+8, 11),
                url: url,
                allDay: true
            },
            {
                title: 'Hindu Festival of Lights \r\n (Earth)',
                start: new Date(y, m+8, 30),
                end: new Date(y, m+9, 4),
                url: url,
                allDay: true
            },
            {
                title: 'Halloween',
                start: new Date(y, m+8, 31),
                url: url,
                allDay: true
            },
            {
                title: 'Starfleet Remembrance Day',
                start: new Date(y, m+9, 11),
                url: url,
                allDay: true
            },
            {
                title: 'Sadie Hawkins Dance',
                start: new Date(y, m+9, 15, 19, 00),
                url: url,
                allDay: false
            },
            {
                title: 'Christmas Day \r\n (Earth)',
                start: new Date(y, m+10, 25),
                url: url,
                allDay: true
            },
            /*
            {
                id: 999,
                title: 'Meeting',
                start: new Date(y, m, d+15, 20, 21),
                allDay: false
            },
            {
                title: 'Meeting',
                start: new Date(y, m, d, 10, 30),
                allDay: false
            }, 
            {
                title: 'Lunch',
                start: new Date(y, m, d, 12, 0),
                end: new Date(y, m, d, 14, 0),
                allDay: false
            },
            {
                title: 'Click for Google',
                start: new Date(y, m, 28),
                end: new Date(y, m, 29),
                url: 'http://google.com/'
            }
            */
        ]
	});
});
