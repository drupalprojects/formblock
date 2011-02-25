/**
 * @file
 *
 * Written by dmitrig01 (Dmitri Gaskin) for CTools; this provides dependent
 * visibility for form items in CTools' ajax forms.
 *
 * To your $form item definition add:
 * - '#process' => array('CTools_process_dependency'),
 * - Add '#dependency' => array('id-of-form-item' => array(list, of, values, that,
     make, this, item, show),
 *
 * Special considerations:
 * - radios are harder. Because Drupal doesn't give radio groups individual ids,
 *   use 'radio:name-of-radio'
 *
 * - Checkboxes don't have their own id, so you need to add one in a div
 *   around the checkboxes via #prefix and #suffix. You actually need to add TWO
 *   divs because it's the parent that gets hidden. Also be sure to retain the
 *   'expand_checkboxes' in the #process array, because the CTools process will
 *   override it.
 */

(function ($) {
  Drupal.formblock = Drupal.formblock || {};
  Drupal.formblock.dependent = {};

  Drupal.formblock.dependent.bindings = {};
  Drupal.formblock.dependent.activeBindings = {};
  Drupal.formblock.dependent.activeTriggers = [];

  Drupal.formblock.dependent.inArray = function(array, search_term) {
    var i = array.length;
    if (i > 0) {
     do {
      if (array[i] == search_term) {
         return true;
      }
     } while (i--);
    }
    return false;
  }


  Drupal.formblock.dependent.autoAttach = function() {
    // Clear active bindings and triggers.
    for (i in Drupal.formblock.dependent.activeTriggers) {
      jQuery(Drupal.formblock.dependent.activeTriggers[i]).unbind('change');
    }
    Drupal.formblock.dependent.activeTriggers = [];
    Drupal.formblock.dependent.activeBindings = {};
    Drupal.formblock.dependent.bindings = {};

    if (!Drupal.settings.formblock) {
      return;
    }

    // Iterate through all relationships
    for (id in Drupal.settings.formblock.dependent) {

      // Drupal.formblock.dependent.activeBindings[id] is a boolean,
      // whether the binding is active or not.  Defaults to no.
      Drupal.formblock.dependent.activeBindings[id] = 0;
      // Iterate through all possible values
      for(bind_id in Drupal.settings.formblock.dependent[id].values) {
        // This creates a backward relationship.  The bind_id is the ID
        // of the element which needs to change in order for the id to hide or become shown.
        // The id is the ID of the item which will be conditionally hidden or shown.
        // Here we're setting the bindings for the bind
        // id to be an empty array if it doesn't already have bindings to it
        if (!Drupal.formblock.dependent.bindings[bind_id]) {
          Drupal.formblock.dependent.bindings[bind_id] = [];
        }
        // Add this ID
        Drupal.formblock.dependent.bindings[bind_id].push(id);
        // Big long if statement.
        // Drupal.settings.formblock.dependent[id].values[bind_id] holds the possible values

        if (bind_id.substring(0, 6) == 'radio:') {
          var trigger_id = "input[name='" + bind_id.substring(6) + "']";
        }
        else {
          var trigger_id = '#' + bind_id;
        }

        Drupal.formblock.dependent.activeTriggers.push(trigger_id);


        var getValue = function(item, trigger) {
          if (item.substring(0, 6) == 'radio:') {
            var val = jQuery(trigger + ':checked').val();
          }
          else {
            switch (jQuery(trigger).attr('type')) {
              case 'checkbox':
                var val = jQuery(trigger).attr('checked') || 0;
                break;
              default:
                var val = jQuery(trigger).val();
            }
          }
          return val;
        }

        var setChangeTrigger = function(trigger_id, bind_id) {
          // Triggered when change() is clicked.
          var changeTrigger = function() {
            var val = getValue(bind_id, trigger_id);

            for (i in Drupal.formblock.dependent.bindings[bind_id]) {
              var id = Drupal.formblock.dependent.bindings[bind_id][i];

              // Fix numerous errors
              if (typeof id != 'string') {
                continue;
              }

              // This bit had to be rewritten a bit because two properties on the
              // same set caused the counter to go up and up and up.
              if (!Drupal.formblock.dependent.activeBindings[id]) {
                Drupal.formblock.dependent.activeBindings[id] = {};
              }

              if (Drupal.formblock.dependent.inArray(Drupal.settings.formblock.dependent[id].values[bind_id], val)) {
                Drupal.formblock.dependent.activeBindings[id][bind_id] = 'bind';
              }
              else {
                delete Drupal.formblock.dependent.activeBindings[id][bind_id];
              }

              var len = 0;
              for (i in Drupal.formblock.dependent.activeBindings[id]) {
                len++;
              }

              var object = jQuery('#' + id + '-wrapper');
              if (!object.size()) {
                object = jQuery('#' + id).parent();
              }

              if (Drupal.settings.formblock.dependent[id].type == 'disable') {
                if (Drupal.settings.formblock.dependent[id].num <= len) {
                  // Show if the element if criteria is matched
                  object.attr('disabled', false);
                  object.children().attr('disabled', false);
                }
                else {
                  // Otherwise hide. Use css rather than hide() because hide()
                  // does not work if the item is already hidden, for example,
                  // in a collapsed fieldset.
                  object.attr('disabled', true);
                  object.children().attr('disabled', true);
                }
              }
              else {
                if (Drupal.settings.formblock.dependent[id].num <= len) {
                  // Show if the element if criteria is matched
                  object.show(0);
                }
                else {
                  // Otherwise hide. Use css rather than hide() because hide()
                  // does not work if the item is already hidden, for example,
                  // in a collapsed fieldset.
                  object.css('display', 'none');
                }
              }
            }
          }

          jQuery(trigger_id).change(function() {
            // Trigger the internal change function
            // the attr('id') is used because closures are more confusing
            changeTrigger(trigger_id, bind_id);
          });
          // Trigger initial reaction
          changeTrigger(trigger_id, bind_id);
        }
        setChangeTrigger(trigger_id, bind_id);
      }
    }
  }

  Drupal.behaviors.formblockDependent = function (context) {
    Drupal.formblock.dependent.autoAttach();

    // Really large sets of fields are too slow with the above method, so this
    // is a sort of hacked one that's faster but much less flexible.
    $("select.formblock-master-dependent:not(.formblock-processed)")
      .addClass('formblock-processed')
      .change(function() {
        var val = $(this).val();
        if (val == 'all') {
          $('.formblock-dependent-all').show(0);
        }
        else {
          $('.formblock-dependent-all').hide(0);
          $('.formblock-dependent-' + val).show(0);
        }
      })
      .trigger('change');
  }
})(jQuery);
