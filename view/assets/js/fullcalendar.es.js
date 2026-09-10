/*!
FullCalendar Core v6.1.15 - Spanish Locale
Docs & License: https://fullcalendar.io
(c) 2024 Adam Shaw
*/
!function(){"use strict";var e={code:"es",week:{dow:1,doy:4},buttonText:{prev:"Ant",next:"Sig",today:"Hoy",year:"Año",month:"Mes",week:"Semana",day:"Día",list:"Agenda"},buttonHints:{prev:"$0 antes",next:"$0 siguiente",today:function(e){return"Día"===e?"Hoy":("Semana"===e?"Esta":"Este")+" "+e.toLocaleLowerCase()}},viewHint:function(e){return"Vista "+("Semana"===e?"de la":"del")+" "+e.toLocaleLowerCase()},weekText:"Sm",weekTextLong:"Semana",allDayText:"Todo el día",moreLinkText:"más",moreLinkHint:function(e){return"Mostrar "+e+" eventos más"},noEventsText:"No hay eventos para mostrar",navLinkHint:"Ir al $0",closeHint:"Cerrar",timeHint:"La hora",eventHint:"Evento"};if(typeof FullCalendar!=="undefined"&&FullCalendar.globalLocales){FullCalendar.globalLocales.push(e);}}();