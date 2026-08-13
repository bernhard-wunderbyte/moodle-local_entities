// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/*
 * @package    local_entities
 * @copyright  Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Calendar} from 'local_entities/fullcalendar';
import Ajax from 'core/ajax';

var initialLocaleCode = "";
var calendarEl = "";
var calendarFirstDay = 1;
var calendarHour12 = false;

/**
 * Init Calendar
 * @param {entityid} entityid
 * @param {string} locale
 * @param {string} jsondata
 * @param {number|string} firstday first day of the week (0=Sunday, 1=Monday, 6=Saturday)
 * @param {number|string} timeformat 24 (24-hour) or 12 (am/pm)
 */
export const init = (entityid, locale, jsondata = null, firstday = 1, timeformat = 24) => {
    initialLocaleCode = locale;
    calendarFirstDay = parseInt(firstday, 10);
    if (isNaN(calendarFirstDay)) {
        calendarFirstDay = 1;
    }
    calendarHour12 = parseInt(timeformat, 10) === 12;
    calendarEl = document.getElementById('entity-calendar');
    if (!jsondata) {
        jsondata = getEntityCalendardata(entityid);
    } else {
        renderCalendar(jsondata);
    }
};

const renderCalendar = (events) => {
    // Explicit formats so the configured week start and time format win over the locale defaults
    // (with an empty or English locale FullCalendar would render Sunday-first with am/pm times).
    // The 12h branch needs an explicit hour12 flag: with a 24-hour locale (e.g. German) Intl
    // ignores the meridiem request and keeps rendering 24-hour times otherwise.
    var eventtimeformat = calendarHour12
        ? {hour: 'numeric', minute: '2-digit', meridiem: 'short', hour12: true}
        : {hour: '2-digit', minute: '2-digit', hour12: false};
    var slotlabelformat = calendarHour12
        ? {hour: 'numeric', meridiem: 'short', hour12: true}
        : {hour: '2-digit', minute: '2-digit', hour12: false};
    var calendar = new Calendar(calendarEl, {
        timeZone: 'UTC',
        firstDay: calendarFirstDay,
        eventTimeFormat: eventtimeformat,
        slotLabelFormat: slotlabelformat,
        eventStartEditable: false,
        displayEventEnd: true,
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        locale: initialLocaleCode,
        buttonIcons: false,
        weekNumbers: true,
        navLinks: true,
        editable: true,
        dayMaxEvents: true,
        events: events,
        buttonText: {
          prev: "Zur\xFCck",
          next: "Vor",
          today: "Heute",
          year: "Jahr",
          month: "Monat",
          week: "Woche",
          day: "Tag",
          list: "Termin\xFCbersicht"
        },
        weekText: "KW",
        weekTextLong: "Woche",
        allDayText: "Ganzt\xE4gig",
        moreLinkText: function(n) {
          return "+ weitere " + n;
        },
        noEventsText: "Keine Ereignisse anzuzeigen",
        buttonHints: {
          prev: function(buttonText) {
            return "Vorherige".concat(affix(buttonText), " ").concat(buttonText);
          },
          next: function(buttonText) {
            return "N\xE4chste".concat(affix(buttonText), " ").concat(buttonText);
          },
          today: function(buttonText) {
            if (buttonText === "Tag") {
              return "Heute";
            }
            return "Diese".concat(affix(buttonText), " ").concat(buttonText);
          }
        },
        viewHint: function(buttonText) {
          // eslint-disable-next-line no-nested-ternary
          var glue = buttonText === "Woche" ? "n" : buttonText === "Monat" ? "s" : "es";
          return buttonText + glue + "ansicht";
        },
        navLinkHint: "Gehe zu $0",
        moreLinkHint: function(eventCnt) {
          return "Zeige " + (eventCnt === 1 ? "ein weiteres Ereignis" : eventCnt + " weitere Ereignisse");
        },
        closeHint: "Schlie\xDFen",
        timeHint: "Uhrzeit",
        eventHint: "Ereignis",
        eventDidMount: function(info) {
            if (info.event.classNames.includes('entities-cancelled')) {
                var titleEl = info.el.querySelector('.fc-event-title');
                if (titleEl) {
                    var fullTitle = titleEl.textContent;
                    var bracketEnd = fullTitle.indexOf('] ');
                    if (bracketEnd !== -1) {
                        var prefix = fullTitle.substring(0, bracketEnd + 2);
                        var rest = fullTitle.substring(bracketEnd + 2);
                        titleEl.innerHTML =
                            '<span class="entities-cancelled-prefix">' + prefix + '</span>' +
                            rest;
                    }
                }
            }
        }
      });
      calendar.render();
};

/**
 * Returns the right affix
 * @param {string} buttonText
 * @returns {string}
 */
function affix(buttonText) {
    // eslint-disable-next-line no-nested-ternary
    return buttonText === "Tag" || buttonText === "Monat" ? "r" : buttonText === "Jahr" ? "s" : "";
}

/**
 * Get Calendardata via WS.
 * @param {integer} entityid
 *
 */
const getEntityCalendardata = (entityid) => {
    let request = {
        methodname: 'local_entities_get_entity_calendardata',
        args: {'id': entityid}
    };
    Ajax.call([request])[0].done(function(data) {
        if (data.json) {
            renderCalendar(JSON.parse(data.json));
        } else {
            // eslint-disable-next-line no-console
            console.log(data.error);
        }
    }).fail();
};
