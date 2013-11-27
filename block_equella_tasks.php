<?php

// This file is part of the EQUELLA Moodle Integration - https://github.com/equella/moodle-block-tasks
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

require_once($CFG->dirroot.'/mod/equella/common/lib.php');
require_once($CFG->dirroot.'/mod/equella/common/soap.php');

class block_equella_tasks extends block_list {

    function init() {
        $this->title = get_string('pluginname', 'block_equella_tasks');
    }

    function get_content() {
        global $CFG, $COURSE, $SESSION;

        if( $this->content !== NULL ) {
            return $this->content;
        }

        if( empty($this->instance) || !isloggedin() || isguestuser() ) {
            return null;
        }

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        if (empty($this->instance->pageid)) { // sticky
            if (!empty($COURSE)) {
                $this->instance->pageid = $COURSE->id;
            }
        }

        $cache = null;
        if( isset($SESSION->equella_tasks) ) {
            $cache = $SESSION->equella_tasks;
        }

        if( empty($cache) or $cache->expires < time() ) {

            $cache = new stdClass();
            $cache->items = array();
            $cache->icons = array();
            $cache->expires = time() + (5 * 60);

            try {
                $token = equella_getssotoken();
                $equella = new EQUELLA(equella_soap_endpoint());

                // Check that 'getTaskFilterCounts' is available
                if( !$equella->hasMethod('getTaskFilterCounts') ) {
                    $cache->items[]= get_string('incompatible', 'block_equella_tasks');
                    $cache->icons[]= '<img src="'.$CFG->wwwroot.'/mod/equella/pix/icon-red.gif" class="icon" alt="" />';
                } else {
                    $equella->loginWithToken($token);
                    $filtersXml = $equella->getTaskFilterCounts(true);
                    $filters = $filtersXml->nodeList('/filters/filter');

                    if( $filters->length == 0 ) {
                        $cache->items[]= get_string('notasks', 'block_equella_tasks');
                    } else {
                        foreach( $filters as $filter ) {
                            $name = $filtersXml->nodeValue('name', $filter).' - '.$filtersXml->nodeValue('count', $filter);
                            $href = equella_appendtoken($filtersXml->nodeValue('href', $filter), $token);
                            $cache->items[]= '<a target="_blank" href="'.$href.'">'.$name.'</a>';
                        }
                    }
                }
            } catch( Exception $e ) {
                $cache->items[]= get_string('error', 'block_equella_tasks');
                $cache->icons[]= '<img src="'.$CFG->wwwroot.'/mod/equella/pix/icon-red.gif" class="icon" alt="" />';
            }

            $SESSION->equella_tasks = $cache;
        }

        $this->content->items = $cache->items;
        $this->content->icons = $cache->icons;

        return $this->content;
    }
}
