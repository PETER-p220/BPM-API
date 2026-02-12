<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\TenderController;
use App\Http\Controllers\AssignTenderController;
use App\Http\Controllers\TenderDocSubmissionController;
use App\Http\Controllers\ProjectController;

class DashboardController extends Controller
{
    /**
     * Get all dashboard statistics in a single API call
     */
    public function getDashboardStats(Request $request)
    {
        try {
            // Initialize controllers
            $tenderController = new TenderController();
            $assignTenderController = new AssignTenderController();
            $tenderSubmissionController = new TenderDocSubmissionController();
            $projectController = new ProjectController();

            // Get all tender statistics
            $tenders = [
                'registered' => $this->getCount($tenderController, 'countTenders'),
                'assigned' => $this->getCount($assignTenderController, 'countAllAssignedTenders'),
                'submitted' => $this->getCount($tenderSubmissionController, 'countSubmissions'),
                'inProgress' => $this->getCount($assignTenderController, 'countOnProgressTenders'),
                'deadlineReached' => $this->getCount($assignTenderController, 'countAllDeadlineReachedTenders'),
                'expired' => $this->getCount($assignTenderController, 'countAllExpiredTenders')
            ];

            // Get all project statistics
            $projects = [
                'total' => $this->getCount($projectController, 'countProjects'),
                'inProgress' => $this->getCount($projectController, 'countAllOnProgressProjects'),
                'completed' => $this->getCount($projectController, 'countCompletedProjects'),
                'failed' => $this->getCount($projectController, 'countFailedProjects')
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'tenders' => $tenders,
                    'projects' => $projects
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load dashboard statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to safely get count from controller methods
     */
    private function getCount($controller, $method)
    {
        try {
            $response = $controller->$method();
            $data = $response->getData(true);
            
            // Extract count from various response formats
            if (isset($data['count'])) {
                return (int) $data['count'];
            } elseif (isset($data['registered_tenders'])) {
                return (int) $data['registered_tenders'];
            } elseif (isset($data['assignedCount'])) {
                return (int) $data['assignedCount'];
            } elseif (isset($data['submitted_tenders'])) {
                return (int) $data['submitted_tenders'];
            } elseif (isset($data['onProgressCount'])) {
                return (int) $data['onProgressCount'];
            } elseif (isset($data['expired_tenders'])) {
                return (int) $data['expired_tenders'];
            } elseif (isset($data['count_total_projects'])) {
                return (int) $data['count_total_projects'];
            } elseif (isset($data['total_on_progress_projects'])) {
                return (int) $data['total_on_progress_projects'];
            } elseif (isset($data['total_completed_projects'])) {
                return (int) $data['total_completed_projects'];
            } elseif (isset($data['total_failed_projects'])) {
                return (int) $data['total_failed_projects'];
            }
            
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
