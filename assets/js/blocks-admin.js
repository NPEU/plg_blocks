/*

*/
async function getData(url) {
    try {
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`Response status: ${response.status}`);
        }
        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            throw new TypeError("Oops, we haven't got JSON!");
        }

        const result = await response.json();
        return result;
    } catch (error) {
        console.error(error.message);
    }
}

(function () {

    //var self = this;

    var murl = '/administrator/index.php?option=com_ajax&format=json&plugin=getModuleById&module_id=';

    var tidy_preview_html = function (html) {
        if (!html) {
            return html;
        }
        html = html.replace(/src="([^/])/g, 'src="/$1');
        return html;
    }

    var init = function () {

        //console.log(document.getElementById('jform_id').value);
        if (!document.getElementById('jform_id')) {
            return;
        }
        var page_id = document.getElementById('jform_id').value;

        if (page_id == 0) {
            document.querySelector('#fieldset-blocks .form-grid').hidden = true;
            document.querySelector('#fieldset-blocks legend + .alert-info .visually-hidden').nextSibling.textContent = ' Important: You must save this menu item before you can add blocks to it.';
        }


        document.querySelectorAll('#fieldset-blocks .subform-repeatable').forEach(function (subform) {


            subform.addEventListener("subform-row-add", (event) => {
                subform_init(event.target);
            });

            subform_init(subform);
        });

        const msgListener = event => {
            //console.log(event.data);
            // Avoid cross origins
            if (event.origin !== window.location.origin) return;
            // Check message type
            if (event.data.messageType === 'joomla:content-select') {

                if (!window.modal_preview_container) {
                    return;
                }
                //var block_cell = window.modalInitiatorBlock;
                //var preview_container = block_cell.querySelector('.block_preview');

                var mid = event.data.id;
                if (mid > 0) {
                    var url = murl + mid;
                    //console.log(mid);
                    getData(url).then(result => {
                        // do things with the result here, like call functions with them
                        console.log(result.data[0]);
                        console.log(result.data[0].rendered_output);

                        //preview_container.innerHTML = result.data[0].rendered_output;

                        window.modal_preview_container.innerHTML = tidy_preview_html(result.data[0].rendered_output);
                        window.modal_preview_container = null;
                    });
                }
            }
        };
        // Use a JoomlaExpectingPostMessage flag to be able to distinct legacy methods
        // @TODO: This should be removed after full transition to postMessage()
       //window.JoomlaExpectingPostMessage = true;
        window.addEventListener('message', msgListener);
    };

    var subform_init = function (subform) {

        //console.log("BLOCKS: subform_init()");
        // Make the layout chooser details element behave more like a select:
        subform.querySelectorAll('details.radio').forEach(function (details) {
            subform.querySelectorAll('label').forEach(function (label) {
                label.addEventListener("click", (event) => {
                    details.open = false;
                });
            });
        });

        var block_preview_classname = 'block_preview';

        subform.querySelectorAll('.block-cell').forEach(function (block_cell) {

            const  modal_type_select = block_cell.querySelector('.form-select');
            const  modal_control     = modal_type_select.nextElementSibling;
            const  modal_input       = modal_control.querySelector('input.form-control');
            const  modal_select      = block_cell.querySelector('[data-button-action="select"]');
            const  modal_create      = block_cell.querySelector('[data-button-action="create"]');
            const  modal_edit        = block_cell.querySelector('[data-button-action="edit"]');
            const  modal_clear       = block_cell.querySelector('[data-button-action="clear"]');
            const  modal_hidden      = block_cell.querySelector('input[type="hidden"]');
            let    preview_container = block_cell.lastElementChild;


            // Add the classes input to block cell:
            const originalLabelWrap = block_cell.querySelector('.control-label:first-child');
            //if (!originalLabelWrap) return;

            const originalLabel = originalLabelWrap.querySelector('label');
            //if (!originalLabel) return;

            // Find the matching input for the cloned "classes" field
            const classesInputId = originalLabel.id.replace(/_id-lbl$/, '_classes');
            const classesInput = document.getElementById(classesInputId);
            classesInput.type = 'text';
            classesInput.className = 'form-control';
            //if (!classesInput) return;

            // Clone the label wrapper
            const clonedLabelWrap = originalLabelWrap.cloneNode(true);
            const clonedLabel = clonedLabelWrap.querySelector('label');

            // Update cloned label attributes
            clonedLabel.id = classesInput.id + '-lbl';
            clonedLabel.htmlFor = classesInput.id;

            // Optional: adjust visible text
            clonedLabel.textContent = originalLabel.textContent.replace(/\bcontent\b/i, 'classes');

            // Insert cloned label before the original one
            block_cell.insertBefore(clonedLabelWrap, originalLabelWrap);

            // Create a controls wrapper and move the input into it
            const controls = document.createElement('div');
            controls.className = 'controls';
            controls.appendChild(classesInput);

            // Insert controls before the original label
            block_cell.insertBefore(controls, originalLabelWrap);









            //var first_control_label = block_cell.querySelector('.control-label:first-child');
            //var first_label = first_control_label.querySelector('label');
            //var hidden_input = document.getElementById(first_label.id.replace(/id-lbl$/, 'classes'));
            //hidden_input.type = 'text';
            //console.log(hidden_input);





            // jform_params__rows__rows0__block_1_classes
            // jform_params__rows__rows0__block_1_id
            //console.log(modal_input.id);
            //var n = block_cell.className.replace(/[^0-9]*/g, '');

            //var class_input_id = modal_input.id.replace(/_id$/, '_classes');
            //var class_input = document.getElementById(class_input_id);
            //var block_content_label = document.getElementById(modal_input.id + '-lbl');

            //var class_input_chunk = block_content_label.parentElement.cloneNode(true);
            //class_input_chunk.id = class_input_chunk.id.replace('_id-lbl', '_classes-lbl');
            //class_input_chunk.setAttribute('for', 'testing');
            //console.log(class_input_chunk);




            //class_input.type = 'text';

            //block_cell.prepend(class_input_chunk);

            if (preview_container && !preview_container.classList.contains(block_preview_classname)) {
                preview_container = document.createElement("div");

                preview_container.className = block_preview_classname;
                block_cell.append(preview_container);
            }

            modal_input.dataset.placeholderOrginial = modal_input.placeholder;

            modal_create.dataset.modalConfigOriginal = modal_create.dataset.modalConfig;
            modal_select.dataset.modalConfigOriginal = modal_select.dataset.modalConfig;

            //console.log(modal_hidden.value);
            if (modal_hidden.value > 0) {

                modal_type_select.hidden = true;



                var url = murl + modal_hidden.value;
                //console.log(mid);
                getData(url).then(result => {
                    // do things with the result here, like call functions with them
                    //console.log(result.data[0]);

                    modal_input.value = result.data[0].title;
                    preview_container.innerHTML = tidy_preview_html(result.data[0].rendered_output);
                    modal_control.hidden = false;
                });

            } else {modal_control.hidden = false;
                modal_control.hidden = true;
                modal_type_select.hidden = false;
            }

            modal_create.addEventListener("click", (event) => {
                //window.modalInitiatorBlock = block_cell;
                window.modal_preview_container = preview_container;
                //window.modal_mid = modal_hidden.value;
            });

            modal_edit.addEventListener("click", (event) => {
                //window.modalInitiatorBlock = block_cell;
                window.modal_preview_container = preview_container;
                //window.modal_mid = modal_hidden.value;
            });

            modal_clear.addEventListener("click", (event) => {
                modal_control.hidden = true;
                modal_type_select.hidden = false;
                modal_type_select.selectedIndex = 0;

                modal_input.value = "";
                modal_input.placeholder = modal_input.placeholder = modal_input.dataset.placeholderOrginial;
                modal_create.dataset.modalConfig = modal_create.dataset.modalConfigOriginal;
                modal_select.dataset.modalConfig = modal_select.dataset.modalConfigOriginal;

                preview_container.innerHTML = "";
            });

            modal_type_select.addEventListener("change", (event) => {
                //result.textContent = `You like ${event.target.value}`;
                console.log('HERE: ' + event.target.value);

                if (event.target.value == 0) {
                    modal_control.hidden = true;
                }  else {
                    //console.log(btn);
                    modal_control.hidden = false;
                    modal_type_select.hidden = true;

                    var selected_text  = modal_type_select.options[modal_type_select.selectedIndex].text;
                    var selected_value = modal_type_select.options[modal_type_select.selectedIndex].value;

                    // This may be best determined in a different way:
                    var selected_modtype = selected_text.replace(' ', '').toLowerCase();

                    //console.log(selected_text);
                    //console.log(selected_value);
                    console.log(selected_modtype);

                    modal_input.placeholder = modal_input.placeholder.replace('{TYPE}', selected_text);



                    modal_create.dataset.modalConfigOriginal = modal_create.dataset.modalConfig;
                    modal_create.dataset.modalConfig = modal_create.dataset.modalConfig.replace('{EID}', selected_value);


                    modal_select.dataset.modalConfigOriginal = modal_select.dataset.modalConfig;
                    modal_select.dataset.modalConfig = modal_select.dataset.modalConfig.replace('{modtype}', selected_modtype);

                    modal_clear.hidden = false;

                }
            });

            // Callback function to execute when mutations are observed
            /*const callback = (mutationList, observer) => {
                for (const mutation of mutationList) {
                    if (mutation.type === "attributes" && mutation.attributeName == 'value') {
                        var mid = mutation.target.getAttribute(mutation.attributeName);
                        var url = murl + mid;
                        //console.log(mid);
                        getData(url).then(result => {
                            // do things with the result here, like call functions with them
                            console.log(result.data[0].rendered_output);

                            preview_container.innerHTML = result.data[0].rendered_output;
                        });


                    }
                }
            };

            // Create an observer instance linked to the callback function
            const observer = new MutationObserver(callback);

            // Start observing the target node for configured mutations
            observer.observe(modal_hidden, {attributes: true});*/

            // Later, you can stop observing
            //observer.disconnect();

        });

    };




    /*const querySelectorFrom = (selector, elements) => {
        const elementsArr = [...elements];
        return [...document.querySelectorAll(selector)].filter(elm => elementsArr.includes(elm));
    }*/

    const ready = function(fn) {
        if (document.attachEvent ? document.readyState === "complete" : document.readyState !== "loading") {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    ready(init);
})();
