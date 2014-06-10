<?php
/**
 * Copyright (c) Enalean, 2014. All rights reserved
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/
 */

require_once 'TuleapClient/TrackerFactory.php';
require_once 'TuleapClient/Request.php';
require_once 'vendor/autoload.php';

class ContentGenerator {

    const NB_OF_SPRINTS = 50;
    const NB_OF_STORIES = 1000;
    const NB_OF_TASKS   = 100;

    private $project_name;

    /** @var Client */
    private $request;
    private $tracker_factory;

    public function __construct($project_name, $username, $password) {
        $this->project_name = $project_name;
        $this->request = new \TuleapClient\Request(
            new \Guzzle\Http\Client(
                'https://YOUR.SERVER.HERE/api',
                array(
                    'ssl.certificate_authority' => false
                )
            ),
            $username,
            $password
        );
        $this->tracker_factory = new TuleapClient\TrackerFactory(
            $this->request,
            $this->getProject($this->project_name)
        );
    }

    public function setUp() {
        try {
            $story_tracker = $this->tracker_factory->getTrackerRest('agile_story');
            $pool_of_stories = array();
            for ($i = 0; $i < self::NB_OF_STORIES; ++$i) {
                $pool_of_tasks = array();

                for ($j = 0; $j < self::NB_OF_TASKS; ++$j) {
                    $pool_of_tasks[] = $this->appendTask("Task ".$i." ".$j);
                }

                $summary = "Story ". $i;
                $this->startBench($summary);
                $story = $story_tracker->createArtifact(
                    array(
                        $story_tracker->getSubmitScalarValue("I want to", $summary),
                        $story_tracker->getSubmitScalarValue("Story Points", 10),
                        $story_tracker->getSubmitScalarValue("Remaining Story Points", 10),
                        $story_tracker->getSubmitListValue("Status", "To be done"),
                        $story_tracker->getSubmitArtifactLinkValue($pool_of_tasks)
                    )
                );
                $this->endBench($summary);
                $pool_of_stories[] = $story['id'];
            }


            $sprint_tracker = $this->tracker_factory->getTrackerRest('agile_sprint');
            $pool_of_sprints = array();

            for ($i = 0; $i < self::NB_OF_SPRINTS; ++$i) {
                $sprint = $sprint_tracker->createArtifact(
                    array(
                        $sprint_tracker->getSubmitScalarValue("Name", "Sprint " . $i),
                        $sprint_tracker->getSubmitListValue("Status", "Done"),
                        $sprint_tracker->getSubmitScalarValue("Start Date", date('c', strtotime('previous monday'))),
                        $sprint_tracker->getSubmitScalarValue("Duration", '10'),
                        $sprint_tracker->getSubmitScalarValue("Capacity", '12'),
                    )
                );

                $pool_of_sprints[] = $sprint['id'];
            }

            $release_tracker = $this->tracker_factory->getTrackerRest('agile_release');
            $release_1 = $release_tracker->createArtifact(
                array(
                    $release_tracker->getSubmitScalarValue("Name", "2.0"),
                    $release_tracker->getSubmitScalarValue("Start Date", date('c', strtotime('previous monday'))),
                    $release_tracker->getSubmitScalarValue("Duration", '30'),
                    $release_tracker->getSubmitListValue('Status', 'Current'),
                    $release_tracker->getSubmitArtifactLinkValue(array_merge($pool_of_sprints, $pool_of_stories)),
                )
            );
        }
        catch (Guzzle\Http\Exception\BadResponseException $exception) {
            echo $exception->getRequest();
            echo $exception->getResponse()->getBody(true);
            die(PHP_EOL);
        }
    }

    private function getProject($name) {
        $offset = 0;
        $limit  = 10;
        do {
            $json = $this->request->getJson($this->request->getClient()->get("projects?offset=$offset&limit=$limit"));
            foreach ($json as $project) {
                if ($project['label'] === $name) {
                    return $project;
                }
            }
            $offset += $limit;
        } while(count($json));
        throw new Exception("Project '$name' not found");
    }

    private function appendTask($summary) {
        $this->startBench($summary);
        $task_tracker = $this->tracker_factory->getTrackerRest('agile_task');
        $task_1 = $task_tracker->createArtifact(
            array(
                $task_tracker->getSubmitScalarValue("Summary", $summary),
                $task_tracker->getSubmitListValue("Status", "To be done"),
            )
        );
        $this->endBench($summary);
        return $task_1['id'];
    }

    private $benchs = array();
    private function startBench($summary) {
        $this->benchs[$summary] = microtime(1);
    }

    private function endBench($summary) {
        echo "Created $summary in ". round(microtime(1) - $this->benchs[$summary], 3) ."s". PHP_EOL;
    }
}

$creator = new ContentGenerator("YOUR PROJECT HERE", "YOUR USERNAME HERE", "YOUR PASSWORD HERE");

$creator->setUp();
