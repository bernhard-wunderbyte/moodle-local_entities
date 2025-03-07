<?php
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

/**
 * Handler for entities relations
 * @package    local_entities
 * @copyright  2021 Wunderbyte GmbH
 * @author     Thomas Winkler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_entities;

defined('MOODLE_INTERNAL') || die();
define('LOCAL_ENTITIES_FORM_ENTITYID', 'local_entities_entityid_');
define('LOCAL_ENTITIES_FORM_ENTITYAREA', 'local_entities_entityarea_');
define('LOCAL_ENTITIES_FORM_RELATIONID', 'local_entities_relationid_');
define('LOCAL_ENTITIES_FORM_NAME', 'local_entities_entityname_');

global $CFG;
require_once("$CFG->libdir/formslib.php");

use core_form\external\dynamic_form;
use moodle_exception;
use moodle_recordset;
use MoodleQuickForm;
use stdClass;

/**
 * Control and manage option dates.
 *
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @author Thomas Winkler
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entitiesrelation_handler {

    /** @var string $component */
    public $component = '';

    /** @var string $area */
    public $area = '';

    /** @var int $instanceid */
    public $instanceid = 0;

    /**
     * Constructor.
     * @param string $component
     * @param string $area
     * @param int $instanceid
     */
    public function __construct(string $component, string $area, int $instanceid = 0) {
        $this->component = $component;
        $this->area = $area;
        $this->instanceid = $instanceid;
    }

    /**
     * Add form fields to be passed on mform.
     *
     * @param MoodleQuickForm $mform
     * @param int $index // We use the index if we have more than one entity in the form.
     * @param string $formmode
     * @param string|null $headerlangidentifier
     * @param string|null $headerlangcomponent
     * @param int $entityid optional entity id
     *
     * @return array
     *
     */
    public function instance_form_definition(
            MoodleQuickForm &$mform,
            int $index = 0,
            string $formmode = 'expert',
            ?string $headerlangidentifier = null,
            ?string $headerlangcomponent = null,
            int $entityid = 0
    ) {
        global $DB, $PAGE;

        // Workaround: Only show, if it is not turned off in the option form config.
        // We currently need this, because hideIf does not work with headers.
        // In expert mode, we always show everything.
        $showelements = true;
        $showheader = true;
        $elements = [];

        if (!empty($headerlangidentifier)) {
            $header = get_string($headerlangidentifier, $headerlangcomponent);
        } else {
            $header = get_string('addentity', 'local_entities');
        }
        // With the noheader mode, we show the entity but not the header.
        if ($formmode == 'noheader') {
            $showheader = false;
        } else if ($formmode !== 'expert') {
            $cfgentityheader = $DB->get_field('booking_optionformconfig', 'active',
                ['elementname' => 'entitiesrelation']);
            if ($cfgentityheader == 0) {
                $showelements = false;
            }
        }

        if ($showelements) {
            if ($showheader) {
                $mform->addElement('header', 'entitiesrelation',
                    '<i class="fa fa-fw fa-building" aria-hidden="true"></i>&nbsp;' .
                    $header);
                $mform->setExpanded('entitiesrelation', false);
            }

            $records = \local_entities\entities::list_all_parent_entities();

            $select = [0 => get_string('none', 'local_entities')];
            foreach ($records as $record) {
                $select[$record->id] = $record->name;
            }
            $options = [
                'multiple' => false,
                'noselectionstring' => get_string('none', 'local_entities'),
                'ajax' => 'local_entities/form_entities_selector',
                'valuehtmlcallback' => function($value) {
                    global $OUTPUT;
                    if (empty($value)) {
                        return get_string('choose...', 'mod_booking');
                    }
                    $entity = \local_entities\entity::load($value);
                    $parentname = "";
                    if ($entity->parentid) {
                        $parententity = \local_entities\entity::load($entity->parentid);
                        $parentname = $parententity->name;
                    }
                    $entitydata = [
                        'name' => $entity->name,
                        'shortname' => $entity->name,
                        'parentname' => $parentname,
                    ];
                    return $OUTPUT->render_from_template('local_entities/form-entities-selector-suggestion', $entitydata);
                },
            ];

            $element = $mform->addElement(
                'autocomplete', LOCAL_ENTITIES_FORM_ENTITYID . $index,
                get_string('er_entitiesname', 'local_entities'),
                [],
                $options);

            if (!empty($entityid)) {
                $element->setValue($entityid);
            }
            $elements[] = $element;
            $elements[] = $mform->addElement('hidden', LOCAL_ENTITIES_FORM_ENTITYAREA . $index, 'optiondate');
            $mform->setType(LOCAL_ENTITIES_FORM_ENTITYAREA . $index, PARAM_TEXT);

            // TODO: Time table feature is currently not working, we need to fix this in a future release.
            // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
            /* $elements[] = $mform->addElement('button', 'openmodal_' . $index, get_string('opentimetable', 'local_entities')); */

            $PAGE->requires->js_call_amd('local_entities/handler', 'init');

            // TODO: Check if this can be removed safely.
            /* $PAGE->requires->css('/local/entities/js/main.css'); */ // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        }

        return $elements;
    }

    /**
     * Alernative function which adds the elements to the elements array.
     * @param MoodleQuickForm $mform
     * @param array $elements
     * @param int $instanceid
     * @param string $formmode
     * @param null|string $headerlangidentifier
     * @param null|string $headerlangcomponent
     * @return array
     */
    public function instance_form_definition_elements(
        MoodleQuickForm &$mform,
        array &$elements,
        int $instanceid = 0,
        string $formmode = 'expert',
        ?string $headerlangidentifier = null,
        ?string $headerlangcomponent = null) {

    }

    /**
     * Function to validate the correct input of entity and mainly it's availability.
     * In order to work, the key "datestobook" has to be present as an array of entitydates.
     * If there is an itemid, then the dates are already booked. If itemid is 0, they are new.
     * This distinction is important to no falsly identify conflict with itself.
     *
     * @param array $data
     * @param array $errors
     * @return void
     */
    public function instance_form_validation(array $data, array &$errors) {

        // First, see if an entitiyid is set. If not, we can proceed right away.
        if (!$entityidkeys = preg_grep('/^local_entities_entityid/', array_keys($data))) {
            // For performance.
            return;
        }

        foreach ($entityidkeys as $entityidkey) {

            if (empty($data[$entityidkey])) {
                // If there is no entityid value found, we don't need to validate.
                continue;
            }

            $area = $data[$entityidkey] == "local_entities_entityid_0" ? 'option' : 'optiondate';
            // Now determine if there are conflicts.
            $conflicts = entities::return_conflicts($data[$entityidkey],
            $data['datestobook'] ?? [],
            $data['optionid'] ?? 0, $area);

            if (!empty($conflicts['conflicts'])) {

                $errors[$entityidkey] = get_string('errorwiththefollowingdates', 'local_entities');

                foreach ($conflicts['conflicts'] as $conflict) {
                    $link = $conflict->link->out();
                    $errors[$entityidkey] .= "<br><a href='$link'>$conflict->name (" .
                        dates::prettify_dates_start_end($conflict->starttime, $conflict->endtime, current_language()) . ")</a>";
                }
            }
            if (!empty($conflicts['openinghours'])) {
                $errors[$entityidkey] .= get_string('notwithinopeninghours', 'local_entities');
            }
        }

        // Validation for entities in combination with mod_booking.
        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /*if ($this->component = 'mod_booking' && $this->area = 'option') {
            $optionid = $this->instanceid;

            // In validation we need to check, if there are optiondates that have "outlier" entities.
            // If so, the outliers must be changed to the main entity before all relations can be saved.
            if (!empty($data['er_saverelationsforoptiondates']) &&
                self::option_has_dates_with_entity_outliers($optionid) &&
                empty($data['confirm:er_saverelationsforoptiondates'])) {
                    $errors['confirm:er_saverelationsforoptiondates'] =
                        get_string('error:er_saverelationsforoptiondates', 'mod_booking');
            }
        }*/
    }

    /**
     * Helper function to check if there are dates with "entity outliers"
     * (e.g. if all dates have set "Classroom" but there is a date that is
     * happening outside and has set "Park").
     * @param int $optionid
     * @return bool true if there are outliers, false if not
     */
    public static function option_has_dates_with_entity_outliers(int $optionid): bool {
        global $DB;
        // If we have "outliers" (deviating entities), we show a confirm box...
        // ...so a user does not overwrite them accidentally.
        $sql = "SELECT COUNT(DISTINCT er.entityid) numberofentities
                FROM {local_entities_relations} er
                JOIN {booking_optiondates} bod
                ON bod.id = er.instanceid
                WHERE er.area = 'optiondate'
                AND bod.optionid = :optionid";
        $params = ['optionid' => $optionid];
        $numberofentities = $DB->get_field_sql($sql, $params);
        if (!empty($numberofentities) && $numberofentities > 1) {
            return true;
        }
        return false;
    }

    /**
     * Function to delete relation between module and entities.
     * @param int $instanceid
     * @return void
     */
    public function delete_relation(int $instanceid): void {
        global $DB;
        $select = sprintf("component = :component AND area = :area AND %s = :instanceid", $DB->sql_compare_text('instanceid'));
        $DB->delete_records_select('local_entities_relations', $select, [
            'component' => $this->component,
            'area' => $this->area,
            'instanceid' => $instanceid,
        ]);
    }

    /**
     * Returns the data for the form.
     * @param int $instanceid
     * @return stdClass
     */
    public function get_instance_data(int $instanceid): stdClass {
        global $DB;
        $sql = "SELECT r.entityid as id, r.id as relationid, r.component, r.area, r.instanceid,
                    e.name, e.shortname, r.timecreated, e.parentid, (
                    SELECT pe.name
                    FROM {local_entities} pe
                    WHERE pe.id=e.parentid) as parentname
                 FROM {local_entities_relations} r
                 JOIN {local_entities} e
                 ON e.id = r.entityid
                 WHERE r.component = '{$this->component}'
                 AND r.area = '{$this->area}'
                 AND r.instanceid = {$instanceid}";
        $fieldsdata = $DB->get_record_sql($sql);
        if (!$fieldsdata) {
            $stdclass = new stdClass();
            return $stdclass;
        }
        return $fieldsdata;
    }

    /**
     * Returns entityid for a given instanceid.
     * @param int $instanceid
     * @return int entityid
     */
    public function get_entityid_by_instanceid(int $instanceid): int {
        global $DB;
        $sql = "SELECT r.entityid
                 FROM {local_entities_relations} r
                 WHERE r.component = '{$this->component}'
                 AND r.area = '{$this->area}'
                 AND r.instanceid = {$instanceid}";
        $entityid = $DB->get_field_sql($sql);
        if (empty($entityid)) {
            return 0;
        }
        return (int) $entityid;
    }

    /**
     * Sets the fields from entitiesrelations to the given form if entry is found in DB
     *
     * @param MoodleQuickForm $mform
     * @param stdClass $instance
     * @param int $instanceid
     * @param int $index
     * @return void
     */
    public function instance_form_before_set_data(MoodleQuickForm &$mform, stdClass $instance, $instanceid = 0, $index = 0) {
        $instanceid = !empty($instanceid) ? $instanceid : 0;
        $fromdb = $this->get_instance_data($instanceid);
        $entityid = isset($fromdb->id) ? $fromdb->id : 0;
        $entityname = isset($fromdb->name) ? $fromdb->name : "";
        $erid = isset($fromdb->relationid) ? $fromdb->relationid : 0;
        $mform->setDefaults([LOCAL_ENTITIES_FORM_RELATIONID . $index => $erid]);
        $mform->setDefaults([LOCAL_ENTITIES_FORM_ENTITYID . $index => $entityid]);
        $mform->setDefaults([LOCAL_ENTITIES_FORM_NAME . $index => $entityname]);
    }

    /**
     * Sets the fields from entitiesrelations to the given form if entry is found in DB
     *
     * @param stdClass $data
     * @param int $instanceid
     * @param int $index
     * @return void
     */
    public function values_for_set_data(stdClass &$data, $instanceid = 0, $index = 0) {
        $instanceid = !empty($instanceid) ? $instanceid : 0;
        $fromdb = $this->get_instance_data($instanceid);

        // Check for empty is important. Otherwise we overwrite form values when any nosubmit button is pressed.
        if (empty($data->{LOCAL_ENTITIES_FORM_ENTITYID . $index})) {
            $data->{LOCAL_ENTITIES_FORM_ENTITYID . $index} = isset($fromdb->id) ? $fromdb->id : 0;
        }
        if (empty($data->{LOCAL_ENTITIES_FORM_NAME . $index})) {
            $data->{LOCAL_ENTITIES_FORM_NAME . $index} = isset($fromdb->name) ? $fromdb->name : "";
        }
        if (empty($data->{LOCAL_ENTITIES_FORM_RELATIONID . $index})) {
            $data->{LOCAL_ENTITIES_FORM_RELATIONID . $index} = isset($fromdb->relationid) ? $fromdb->relationid : 0;
        }
    }

    /**
     * Saves the given data for entitiesrelations, must be called after the instance is saved and id is present
     * Function returns id of newly created or updated entity, if present.
     * Example:
     *   if ($data = $form->get_data()) {
     *     // ... save main instance, set $data->id if instance was created.
     *     $handler->instance_form_save($data);
     *     redirect(...);
     *   }
     *
     * @param stdClass $instance
     * @param int $instanceid
     * @param int $index
     * @return int|void
     */
    public function instance_form_save(stdClass $instance, int $instanceid, int $index = 0) {
        if (empty($instanceid)) {
            throw new \coding_exception('Caller must ensure that id is already set in data before calling this method');
        }
        if (!preg_grep('/^local_entities/', array_keys((array)$instance))) {
            // If this is called with no result, we must delete the handler.
            $this->delete_relation($instanceid);
            return;
        }
        $key = LOCAL_ENTITIES_FORM_ENTITYID . $index;
        if (empty($instance->{$key})) {
            $this->delete_relation($instanceid);
            return;
        }

        $data = new stdClass();
        if (isset($instance->local_entities_relationid)) {
            $data->id = $instance->local_entities_relationid;
        }
        $data->instanceid = $instanceid;
        $data->component = $this->component;
        $data->area = $this->area;
        $data->entityid = $instance->{$key};
        $data->timecreated = time();
        // Delete er if entitiyid is set to -1.
        if ($data->entityid == -1) {
            $this->delete_relation($data->instanceid);
            return;
        }
        if ($this->er_record_exists($data)) {
            return $this->update_db($data);
        } else {
            return $this->save_to_db($data);
        }
    }

    /**
     * This saves a new relation and creates a "fake" form to use the form_save method.
     * If an empty entityid is provided, the relation is deleted.
     *
     * @param int $instanceid
     * @param int $entityid
     * @return void
     */
    public function save_entity_relation($instanceid, $entityid) {

        if (empty($entityid)) {
            $this->delete_relation($instanceid);
            return;
        }

        $instance = new stdClass();

        $instance->{LOCAL_ENTITIES_FORM_ENTITYID . 0} = $entityid;

        $this->instance_form_save($instance, $instanceid, 0);
    }

    /**
     * Saves relation data to DB
     *
     * @param stdClass $data
     * @return void
     */
    public function save_to_db(stdClass $data) {
        global $DB;
        $DB->insert_record('local_entities_relations', $data);
    }

    /**
     * Update relation DB
     *
     * @param stdClass $data
     * @return int
     */
    public function update_db(stdClass $data) {
        global $DB;
        $DB->update_record('local_entities_relations', $data);
    }
    /**
     * Checks if record exists
     *
     * @param stdClass $data
     * @return void
     */
    public function er_record_exists(stdClass &$data) {
        global $DB;
        $select = sprintf("component = :component AND area = :area AND %s = :instanceid", $DB->sql_compare_text('instanceid'));
        if ($id = $DB->get_field_select('local_entities_relations', 'id', $select, [
                'component' => $this->component,
                'area' => $this->area,
                'instanceid' => $data->instanceid,
        ])) {
            $data->id = $id;
            return true;
        }
        return false;
    }

    /**
     * Get an array of all the entities with exactly this name.
     * @param string $entityname
     * @return array
     */
    public function get_entities_by_name(string $entityname) {
        global $DB;

        $sql = "SELECT * FROM {local_entities}
            WHERE " . $DB->sql_like('name', ':entityname', false);
        $params = ['entityname' => $entityname];

        // We see if there are more than one entities with the same name.
        if ($entities = $DB->get_records_sql($sql, $params)) {
            return $entities;
        } else {
            return [];
        }
    }

    /**
     * Get an array of all the entities with exactly this shortname.
     * @param string $shortname
     * @return array
     */
    public function get_entities_by_shortname(string $shortname) {
        global $DB;
        // We see if there are more than one entities with the same shortname.
        if ($entities = $DB->get_records('local_entities', ['shortname' => $shortname])) {
            return $entities;
        } else {
            return [];
        }
    }

    /**
     * Return entities by id.
     *
     * @param int $entityid
     * @return bool|array
     */
    public static function get_entities_by_id(int $entityid) {
        global $DB;

        $sql = "SELECT  ea.id as addressid, e.id as id, e.name, e.shortname, e.description,
                        e.timecreated, e.timemodified, e.status, e.createdby,
                        e.parentid, e.sortorder, e.cfitemid, e.openinghours,
                        e.maxallocation, e.pricefactor,
                        ea.country, ea.city, ea.postcode,
                        ea.streetname, ea.streetnumber, ea.maplink, ea.mapembed,
                        (
                            SELECT pe.name
                            FROM {local_entities} pe
                            WHERE pe.id=e.parentid) as parentname

                FROM {local_entities} e
                LEFT JOIN {local_entities_address} ea
                ON e.id = ea.entityidto
                WHERE e.id = :entityid";
        $params = ['entityid' => $entityid];

        // We might have more than one record, as there might be more than one address.
        if ($entities = $DB->get_records_sql($sql, $params)) {
            return $entities;
        } else {
            return [];
        }
    }

    /**
     * Helper function to remove all entries in local_entities_relations
     * for a specific booking instance (by bookingid).
     * @param int $bookingid the id of the booking instance
     * @return bool $success - true if successful, false if not
     */
    public static function delete_entities_relations_by_bookingid(int $bookingid): bool {
        global $DB;

        if (empty($bookingid)) {
            throw new moodle_exception('Could not clear entries from local_entities_relations because of missing booking id.');
        }

        // Initialize return value.
        $success = true;

        // TODO: In the future, we'll also need to delete relations for optiondates.

        // Get all currently existing entities relations of the booking instance.
        $existingoptions = $DB->get_records('booking_options', ['bookingid' => $bookingid], '', 'id');
        if (!empty($existingoptions)) {
            foreach ($existingoptions as $existingoption) {
                if (!$DB->delete_records('local_entities_relations', [
                    'component' => 'mod_booking',
                    'area' => 'option',
                    'instanceid' => $existingoption->id,
                ])) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Returns pricefactor set in DB. Can be used for automatic pricecalculation used in booking.
     *
     * @param int $id entity id
     * @return float $pricefactor
     */
    public static function get_pricefactor_by_entityid(int $id) {
        global $DB;
        $params = ['id' => $id];
        $pricefactor = $DB->get_field_select('local_entities', 'pricefactor', 'id = :id', $params, IGNORE_MISSING);
        return $pricefactor;
    }

    /**
     * Return a modal
     *
     * @return string
     */
    private static function render_modal() {
        return '<button type="button" class="btn btn-primary" data-toggle="modal"
        data-target=".bd-example-modal-lg">Large modal</button>
        <div class="modal fade bd-example-modal-lg" tabindex="-1"
        role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
        <div class="modal-content">
                ...
                </div>
            </div>
            </div>';
    }

    /**
     * Returns false if items are not similar.
     * @param array $olditem
     * @param array $newitem
     * @return bool
     */
    public static function compare_items(array $olditem, array $newitem) {

        // If the ids are both empty, we don't see a need to update.
        if (empty($olditem['entityid']) && empty($newitem['entityid'])) {
            return true;
        }

        if ($olditem['entityid'] != $newitem['entityid']
            || $olditem['entityarea'] != $newitem['entityarea']) {
                return false;
        }
        return true;
    }
}
