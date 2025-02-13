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
 * ACE block.
 *
 * @package     block_ace
 * @copyright   2021 University of Canterbury
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/ace/locallib.php');

/**
 * ACE block class.
 *
 * @package     block_ace
 * @copyright   2021 University of Canterbury
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ace extends block_base {

    /**
     * Initialise class variables.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_ace');
    }

    /**
     * Defines if the block supports multiple instances on a single page.
     * True results in per instance configuration.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * Sets the applicable formats for the block.
     *
     * @return string[] Array of pages and permissions.
     */
    public function applicable_formats() {
        return ['all' => true];
    }

    /**
     * Returns the block contents.
     *
     * @return stdClass The block contents.
     */
    public function get_content() {
        global $USER, $DB, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        $type = $this->config->graphtype ?? 'student';
        $text = '';

        switch ($type) {
            case 'student':
                $helptext = get_string('studenttitlehelper', 'block_ace');
                $userid = $this->get_userid_from_contextid();

                if (!has_capability('local/ace:viewown', $this->page->context)) {
                    return $this->content;
                } else if ($userid != $USER->id && !has_capability('local/ace:view', $this->page->context)) {
                    return $this->content;
                }

                $graph = local_ace_student_graph($userid, 0, false);
                if (has_capability('local/ace:view', $this->page->context)) {
                    $url = new moodle_url(get_config('local_ace', 'teacherdashboardurl'));
                } else {
                    $url = new moodle_url(get_config('local_ace', 'userdashboardurl'));
                }

                if ($graph === '') {
                    // Display static image when there are no analytics.
                    $title = get_string('viewyourdashboard', 'block_ace');
                    $attributes = array(
                        'src' => $OUTPUT->image_url('graph', 'block_ace'),
                        'alt' => $title,
                        'class' => 'graphimage',
                    );
                    $text = html_writer::link($url, html_writer::empty_tag('img', $attributes));
                    $title = get_string('viewyourdashboard', 'block_ace');
                    $text .= html_writer::link($url, $title, array('class' => 'textlink'));
                } else {
                    $hiddenpref = get_user_preferences('block_ace_student_hidden_graph', false);

                    // Live graph.
                    $livegraph = html_writer::div($graph, 'usergraph', [
                        'style' => 'display: ' . (!$hiddenpref ? 'block' : 'none'),
                        'id' => 'block_ace-live'
                    ]);

                    // Static image.
                    $title = get_string('viewyourdashboard', 'block_ace');
                    $attributes = array(
                        'src' => $OUTPUT->image_url('graph', 'block_ace'),
                        'alt' => $title,
                        'class' => 'graphimage',
                        'style' => 'display: ' . ($hiddenpref ? 'block' : 'none'),
                        'id' => 'block_ace-static'
                    );
                    $staticimage = html_writer::link($url, html_writer::empty_tag('img', $attributes));

                    $text .= $livegraph . $staticimage;
                    $title = get_string('viewyourdashboard', 'block_ace');
                    $text .= html_writer::link($url, $title, array('class' => 'textlink'));
                    // Controls for both.
                    $staticimageebutton = get_string('switchtostaticimage', 'block_ace');
                    $livegraphbutton = get_string('switchtolivegraph', 'block_ace');
                    $text .= html_writer::link('#', $hiddenpref ? $livegraphbutton : $staticimageebutton, [
                        'class' => 'textlink',
                        'id' => 'block_ace-switchgraph'
                    ]);

                    // Convert boolean to string to pass into script.
                    $hiddenpref = $hiddenpref ? 'true' : 'false';
                    user_preference_allow_ajax_update('block_ace_student_hidden_graph', PARAM_BOOL);
                    // Switch between live & static when clicking the switch graph button.
                    $script = <<<EOF
                        let isLiveGraphHidden = {$hiddenpref};
                        document.querySelector('#block_ace-switchgraph').addEventListener('click', () => {
                            isLiveGraphHidden = !isLiveGraphHidden;
                            M.util.set_user_preference("block_ace_student_hidden_graph", isLiveGraphHidden);
                            if (isLiveGraphHidden) {
                                document.querySelector('#block_ace-static').style.display = 'block';
                                document.querySelector('#block_ace-live').style.display = 'none';
                                document.querySelector('#block_ace-switchgraph').innerText = "{$livegraphbutton}";
                            } else {
                                document.querySelector('#block_ace-static').style.display = 'none';
                                document.querySelector('#block_ace-live').style.display = 'block';
                                document.querySelector('#block_ace-switchgraph').innerText = "{$staticimageebutton}";
                            }
                        });
EOF;
                    $text .= html_writer::script($script);
                }

                break;
            case 'course':
                $helptext = get_string('coursetitlehelper', 'block_ace');
                if ($this->page->course->id == SITEID) {
                    return $this->content;
                }
                $coursecontext = context_course::instance($this->page->course->id);
                if (!has_capability('local/ace:view', $coursecontext)) {
                    return $this->content;
                }

                $graph = local_ace_course_graph($this->page->course->id);
                $text = html_writer::div($graph, 'teachergraph');
                break;
            case 'studentwithtabs':
                $helptext = get_string('studentwithtabstitlehelper', 'block_ace');
                $userid = $this->get_userid_from_contextid();

                if (!has_capability('local/ace:viewown', $this->page->context)) {
                    return $this->content;
                } else if ($userid != $USER->id && !has_capability('local/ace:view', $this->page->context)) {
                    return $this->content;
                }

                $courseid = optional_param('course', 0, PARAM_INT);
                $text = local_ace_student_full_graph($userid, $courseid);
                break;
            case 'teachercourse':
                $helptext = get_string('teachercoursetitlehelper', 'block_ace');
                if (!has_capability('local/ace:viewown', $this->page->context)) {
                    return $this->content;
                }
                $text = local_ace_teacher_course_graph($USER->id);
                break;
            case 'activity':
                $helptext = get_string('activitytitlehelper', 'block_ace');
                if (!has_capability('local/ace:view', $this->page->context)) {
                    return $this->content;
                }
                $text = local_ace_course_module_engagement_graph($this->page->context->instanceid);
                break;
            case 'studentteachergraph':
                $helptext = get_string('studentteachergraphtitlehelper', 'block_ace');
                $courseid = optional_param('course', 0, PARAM_INT);
                $userid = $this->get_userid_from_contextid();
                if ($this->page->context->contextlevel == CONTEXT_USER && $userid != $USER->id
                    && has_capability('local/ace:view', $this->page->context)) {
                    $text = local_ace_student_full_graph($userid, $courseid);
                } else if ($this->page->context->contextlevel == CONTEXT_USER && $userid == $USER->id
                    && has_capability('local/ace:view', context_system::instance())) {
                    $text = local_ace_teacher_course_graph($USER->id);
                } else if (has_capability('local/ace:viewown', $this->page->context)) {
                    $text = local_ace_student_full_graph($USER->id, $courseid);
                }
                break;
            default:
                break;
        }
        $helper = '';
        if (!empty($helptext)) {
            $helper = '<a class="btn btn-link p-0" role="button" data-container="body" data-toggle="popover" data-placement="right"
            data-content="<p>'. $helptext.'</p> "
    data-html="true" tabindex="0" data-trigger="focus" data-original-title="" title="">
            <i class="icon fa fa-question-circle text-info fa-fw " title="'. $helptext.'" role="img" aria-label=""></i></a>';
        }

        $header = html_writer::tag('h5', $this->title.$helper,
            ['class' => 'block_ace-card-title']);

        $this->content->text = $header . $text;

        return $this->content;
    }

    /**
     * Returns the user ID from the contextid url parameter.
     * Defaults to current logged-in user if contextid is not available.
     *
     * @return int User ID
     * @throws coding_exception
     * @throws dml_exception
     */
    private function get_userid_from_contextid(): int {
        global $USER, $DB;

        $userid = $USER->id;

        $contextid = optional_param('contextid', 0, PARAM_INT);
        if ($contextid != 0) {
            $context = context::instance_by_id($contextid, IGNORE_MISSING);
            if ($context != null && $context->contextlevel == CONTEXT_USER) {
                $userid = $DB->get_record('user', array('id' => $context->instanceid))->id;
            }
        }

        return $userid;
    }
}
