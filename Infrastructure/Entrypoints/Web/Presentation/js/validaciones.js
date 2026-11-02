/**
 * Solicita confirmación antes de enviar un formulario de eliminación.
 *
 * El formulario debe incluir el atributo `data-name` con el nombre del
 * elemento a eliminar, por ejemplo:
 *   <form ... onsubmit="return confirmDelete(this);" data-name="Juan">
 *
 * Opcionalmente, `data-label` indica qué se elimina (por defecto "al usuario"):
 *   data-label="el vuelo" data-name="AV8523"
 *
 * @param {HTMLFormElement} form
 * @returns {boolean}
 */
function confirmDelete(form) {

    var name = form.getAttribute('data-name');
    var label = form.getAttribute('data-label') || 'al usuario';
    return confirm('¿Eliminar ' + label + ' ' + name + '?');
}