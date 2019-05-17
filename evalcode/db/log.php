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
 * Definition of log events
 *
 * @package   mod_evalcode
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = array(
    array('module'=>'evalcode', 'action'=>'add', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'delete mod', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'download all submissions', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'grade submission', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'lock submission', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'reveal identities', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'revert submission to draft', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'set marking workflow state', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'submission statement accepted', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'submit', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'submit for grading', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'unlock submission', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'update', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'upload', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'view', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'view all', 'mtable'=>'course', 'field'=>'fullname'),
    array('module'=>'evalcode', 'action'=>'view confirm submit evalcode form', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'view grading form', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'view submission', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'view submission grading table', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'view submit evalcode form', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'view feedback', 'mtable'=>'evalcode', 'field'=>'name'),
    array('module'=>'evalcode', 'action'=>'view batch set marking workflow state', 'mtable'=>'evalcode', 'field'=>'name'),
);
