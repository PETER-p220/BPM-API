<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\TenderController;
use App\Http\Controllers\AssignTenderController;
use App\Http\Controllers\TenderDocSubmissionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RequestForProjectController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\AnalysisForProjectRequestController;
use App\Http\Controllers\ProjectActivitiesController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProjectAnalysisController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\MeetingMinuteController;
use App\Http\Controllers\AwardedTenderController;
use App\Http\Controllers\IntentionToAwardController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\CookieController;
use App\Http\Controllers\PriceScheduleController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\AwardLetterController;
use App\Http\Controllers\InsuranceBondController;
use App\Http\Controllers\SecurityDeclarationController;
use App\Http\Controllers\AppointmentLetterController;
use App\Http\Controllers\ProjectExtensionController;
use App\Http\Controllers\RequestForPurchaseController;
use App\Http\Controllers\ExtendRequestController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;


// Public Routes
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthorized user! Please login to access the API'], 401);
})->name('login');

// Authentication Routes
Route::post('/auth/add-user', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::post('/auth/request-reset', [PasswordResetController::class, 'requestPasswordReset']);
Route::post('/auth/password-reset', [PasswordResetController::class, 'resetPassword']);
Route::post('/accept-cookies', [CookieController::class, 'acceptCookies']);

// Protected Routes
Route::middleware(['auth:sanctum', 'token.expiration'])->group(function () {

//user route
Route::get('/all/users', [AuthController::class, 'users']);
Route::get('/users/byname', [AuthController::class, 'dropdownUsersByName']);
Route::get('/users/byrole', [AuthController::class, 'dropdownUsersByRole']);
Route::get('user/profile', [AuthController::class, 'getLoggedUserProfile']);
Route::get('/user/withrole', [AuthController::class, 'getUsersWithRoles']);
Route::post('/update-profile', [AuthController::class, 'updateProfile']);
Route::get('/count/users', [AuthController::class, 'countUsers']);
    
// Dashboard Routes
Route::get('/dashboard/stats', [DashboardController::class, 'getDashboardStats']);

Route::get('/count/requests', [RequestForProjectController::class, 'countRequests']);
Route::get('/count/user/requests/approved', [RequestForProjectController::class, 'countApprovedRequests']);
Route::get('/count/user/requests/rejected', [RequestForProjectController::class, 'countRejectedRequests']);
Route::get('/count/total-receipts', [ReceiptController::class, 'countTotalReceipts']);

// Route to show a specific user by user_id
Route::get('/user/{user_id}', [AuthController::class, 'showUserById']);

// Route to update a specific user by user_id
Route::put('/user/{user_id}', [AuthController::class, 'updateUser']);
Route::delete('/auth/user/{user_id}', [AuthController::class, 'deleteUser']);
Route::get('/audit-trail', [AuthController::class, 'getAuditTrail']);
Route::post('/store-cookies', [AuthController::class, 'storeCookies']);
    

//admin,hod,account and engineers rout drop dropdown
Route::get('/dropdown/hod', [AuthController::class, 'HodDropDown']);
Route::get('/dropdown/engineer', [AuthController::class, 'EngineersDropDown']);
Route::get('/dropdown/accountants', [AuthController::class, 'AccountantsDropDown']);
Route::get('/dropdown/admin', [AuthController::class, 'AdminsDropDown']);
Route::get('/admin-dropdown', [AuthController::class, 'AdminDropDown']);


//roles route
Route::apiResource('/auth/roles', RoleController::class);
Route::get('/count/roles', [RoleController::class, 'countRoles']);


//department Route
Route::apiResource('/departments', DepartmentController::class);
Route::get('/dropdown/department', [DepartmentController::class, 'departmentByDropDown']);
Route::get('/count/departments', [DepartmentController::class, 'countDepartments']);


//tenders route
Route::apiResource('/tenders', TenderController::class);
Route::get('dropdown/tender', [TenderController::class, 'tenderDropDown']);
Route::get('/count/registered-tenders', [TenderController::class, 'countTenders']);
Route::get('/reportTenders', [TenderController::class, 'getTenderReport']);
Route::get('/types/tenders', [TenderController::class, 'getAllTenderTypes']);


//assign tender route
Route::apiResource('/assign/tender', AssignTenderController::class);
 Route::get('/your/tender', [AssignTenderController::class, 'yourTender']);
Route::get('/count/assigned-tenders', [AssignTenderController::class, 'countAssignedTenders']);
Route::get('/report/for-assignedtenders', [AssignTenderController::class, 'getAssignedTenderReport']);
Route::get('/tender/types/for-assignedtenders', [AssignTenderController::class, 'getAllTenderTypesForAssigned']);
Route::get('/count/all-assigned/tenders', [AssignTenderController::class, 'countAllAssignedTenders']);
Route::get('/count/on-progress/tender', [AssignTenderController::class, 'countOnProgressTenders']);
Route::get('/count/expire-tenders', [AssignTenderController::class, 'countExipredTenders']);
Route::get('/count/deadline-reached/tenders', [AssignTenderController::class, 'countDealineReachedTenders']);


Route::get('/count/all/on-progress-tenders', [AssignTenderController::class, 'countAllOnProgressTenders']);
Route::get('/count/all/deadline-reached-tenders', [AssignTenderController::class, 'countAllDeadlineReachedTenders']);
Route::get('/count/all-expired/tenders', [AssignTenderController::class, 'countAllExpiredTenders']);

//tender document route
Route::resource('/submit/tender', TenderDocSubmissionController::class);
Route::get('/submitted/tender', [TenderDocSubmissionController::class, 'yourSubmission']);
Route::get('/count/tenders-submissions', [TenderDocSubmissionController::class, 'countSubmissions']);
Route::get('/submittedtenders-reports', [TenderDocSubmissionController::class, 'getSubmittedTenderReport']);
Route::get('/tender/types/for-submittedtenders', [TenderDocSubmissionController::class, 'getAllTenderTypesForSubmittedOnes']);
Route::get('/count/submitted/tender', [TenderDocSubmissionController::class, 'countSubmittedTenders']);

//awarded tenders
Route::get('/awarded-tender', [AwardedTenderController::class, 'index']);
Route::get('/count/awarded-tenders', [AwardedTenderController::class, 'countAwardedTenders']);


//projects Routes
Route::resource('/projects', ProjectController::class);
Route::get('/dropdown/projects', [ProjectController::class, 'allProjectsDropDown']);
Route::get('/hod/projects', [ProjectController::class, 'hodProjects']);
Route::get('/my/projects', [ProjectController::class, 'yourProjects']);
Route::get('/count/total-projects', [ProjectController::class, 'countProjects']);
Route::get('/count/failed-projects', [ProjectController::class, 'countFailedProjects']);
Route::get('/count/completed-projects', [ProjectController::class, 'countCompletedProjects']);
Route::get('/count/total-budget', [ProjectController::class, 'countTotalBudget']);
Route::get('/reports-for/projects', [ProjectController::class, 'getProjectsReports']);
Route::get('/count/all/on-progress/projects', [ProjectController::class, 'countAllOnProgressProjects']);


//apointment lettter
Route::resource('appointment-letter', AppointmentLetterController::class)->except(['create', 'edit']);
Route::get('logged-user-appointment-letters', [AppointmentLetterController::class, 'loggedUserAppointmentLetter']);


//extention  for project
Route::resource('project-extension', ProjectExtensionController::class);
Route::get('logged-user-project-extensions', [ProjectExtensionController::class, 'loggedUserProjectExtension']);

//request  for purchase
Route::resource('request-for-purchase', RequestForPurchaseController::class);
Route::get('/logged-user/requests', [RequestForPurchaseController::class, 'LoggedUserRequests']);
Route::post('/requests/update', [RequestForPurchaseController::class, 'update']);

//request for projects
Route::resource('requests-for-projects', RequestForProjectController::class);

//extend request 
Route::resource('extend-request', ExtendRequestController::class);
Route::get('/loggedUserExtentions', [ExtendRequestController::class, 'loggedUserExtentions']);
Route::post('/extention/approve', [ExtendRequestController::class, 'update']);

//countes methods for logged user project
Route::get('/count/user/all-projects', [ProjectController::class, 'countAllUserProjects']);
Route::get('/count/user/completed-project', [ProjectController::class, 'countCompletedUserProjects']);
Route::get('/count/user/on-progress-projects', [ProjectController::class, 'countOnProgressUserProjects']);
Route::get('/count/users/failed-projects', [ProjectController::class, 'countFailedUserProjects']);

//count  projects for hod
Route::get('/count/hod-projects', [ProjectController::class, 'countHodProjects']);
Route::get('/count/hod/projects-failed', [ProjectController::class, 'countHodFailedProjects']);
Route::get('/count/hod/projects/completed', [ProjectController::class, 'countHodCompletedProjects']);
Route::get('/count/hod/projects/budget', [ProjectController::class, 'countHodTotalBudget']);

//count projects for user 
Route::get('/count/user-projects', [ProjectController::class, 'countUserProjects']);
Route::get('/count/user/projects-failed', [ProjectController::class, 'countUserFailedProjects']);
Route::get('/count/user/projects-completed', [ProjectController::class, 'countUserCompletedProjects']);
Route::get('/count/user-projects/budget', [ProjectController::class, 'countUserTotalBudget']);

// Route for fetching all projects for a specific user by user_id
Route::get('/users-with-project-summary', [ProjectController::class, 'usersWithProjectSummary']);


//Hod routes
Route::get('/hod/projects', [ProjectController::class, 'hodProjects']);
Route::get('/hod/project/{project_id}', [ProjectController::class, 'hodProjectDetails']);
Route::post('/hod/project/update', [ProjectController::class, 'update']);
Route::post('/hod/project/approve', [ProjectController::class, 'approveProject']);
Route::post('/hod/project/reject', [ProjectController::class, 'rejectProject']);
Route::get('/hod/project/activities', [ProjectActivitiesController::class, 'index']);
Route::get('/hod/project/analysis', [ProjectAnalysisController::class, 'index']);
Route::get('/hod/project/analysis/approved/count', [ProjectAnalysisController::class, 'countApprovedProjectAnalyses']);
Route::get('/hod/project/analysis/total-amount-required', [ProjectAnalysisController::class, 'countTotalAmountRequiredForProject']);
Route::get('/hod/project/analysis/total-amount-required/approved', [ProjectAnalysisController::class, 'countTotalAmountRequiredForApprovedProjectAnalyses']);
Route::get('/hod/project/analysis/total-amount-required/rejected', [ProjectAnalysisController::class, 'countTotalAmountRequiredForRejectedProjectAnalyses']);
Route::get('count/hod-tenders', [TenderController::class, 'countHodTenders']);
Route::get('count/hod-requests', [RequestForProjectController::class, 'countHodRequests']);
Route::get('count/hod-submitted-tenders', [TenderDocSubmissionController::class, 'countHodSubmittedTenders']);
Route::get('/hod/tenders', [TenderController::class, 'hodTenders']);
Route::get('/hod/tender/{tender_id}', [TenderController::class, 'hodTenderDetails']);
Route::post('/hod/tender/update', [TenderController::class, 'update']);
Route::post('/hod/tender/approve', [TenderController::class, 'approveTender']);
Route::post('/hod/tender/reject', [TenderController::class, 'rejectTender']);



//analysis route
Route::apiResource('analysis', AnalysisController::class);
Route::post('/approve-analysis', [AnalysisController::class, 'update']);
Route::get('user-analysis', [AnalysisController::class, 'userAnalysis']);
Route::get('/items-dropdown', [AnalysisController::class, 'ItemsDropdown']);
Route::get('/logged/user-analyses/count', [AnalysisController::class, 'countUserAnalyses']);
Route::get('/user-analyses/approved/count', [AnalysisController::class, 'countApprovedUserAnalyses']);
Route::get('/user-analyses/rejected/count', [AnalysisController::class, 'countRejectedUserAnalyses']);

// update analysis
Route::post('/analysis/update-from-excel', [AnalysisController::class, 'updateFromExcel']);

//price schedule Routes
Route::apiResource('/price-shedules', PriceScheduleController::class);
Route::post('/approve-schedule', [PriceScheduleController::class, 'update']);
Route::get('user-schedule', [PriceScheduleController::class, 'userSchedule']);
Route::get('/user/price-schedules/count', [PriceScheduleController::class, 'countSubmittedPriceSchedules']);
Route::get('/user/price-schedules/passed/count', [PriceScheduleController::class, 'countApprovedPriceSchedules']);
Route::get('/user/price-schedules/rejected/count', [PriceScheduleController::class, 'countRejectedPriceSchedules']);


//award  routes
Route::resource('intention-to-award', IntentionToAwardController::class);
Route::get('logged-user-intentions', [IntentionToAwardController::class, 'loggedUserIntention']); 
Route::get('/intention-reports', [IntentionToAwardController::class, 'IntentionReports']);

Route::resource('award-letter', AwardLetterController::class)->except(['create', 'edit']);
Route::get('logged-user-award-letters', [AwardLetterController::class, 'loggedUserAwardLetter']);
Route::get('awards-reports', [AwardLetterController::class, 'AwardsReports']);



//perfomance  bond
Route::resource('insurance-bond', InsuranceBondController::class)->except(['create', 'edit']);
Route::get('logged-user-insurance-bonds', [InsuranceBondController::class, 'loggedUserInsuranceBond']);
Route::get('/reports/insurance-bond', [InsuranceBondController::class, 'InsBondReports']);

Route::resource('security-declaration', SecurityDeclarationController::class);
Route::get('logged-user-security-declarations', [SecurityDeclarationController::class, 'loggedUserSecurityDeclaration']);
Route::get('/reports/security-declaration', [SecurityDeclarationController::class, 'DeclarationReports']);

// Fetch activities based on user_id(hod's)
Route::get('hod/project/activities', [ProjectActivitiesController::class, 'index']);
// Fetch activities based on iscreated_by(engineers) for the logged-in user's department
Route::get('eng/project/activities', [ProjectActivitiesController::class, 'index1']);
// Fetch all activities
Route::get('all/project/activities', [ProjectActivitiesController::class, 'index2']);
// Store a new activity
Route::post('/project/activities', [ProjectActivitiesController::class, 'store']);
// View a single activity
Route::get('/project/activities/{activity_id}', [ProjectActivitiesController::class, 'show']);
// Update an existing activity
Route::put('/project/activities/{activity_id}', [ProjectActivitiesController::class, 'update']);
// Delete an activity
Route::delete('/project/activities/{activity_id}', [ProjectActivitiesController::class, 'destroy']);
Route::get('/count/project-activities', [ProjectActivitiesController::class, 'countAllActivities']);
Route::get('/count/proj-activity-for-hod', [ProjectActivitiesController::class, 'countUserActivities']);
Route::get('/count/proj-activities/for-user', [ProjectActivitiesController::class, 'countUserV1Projects']);


 //udpdates
 Route::resource('/updates', ChatController::class);
Route::get('/my/updates', [ChatController::class, 'MyChats']);
Route::get('/count/total-updates', [ChatController::class, 'countAllChats']);
Route::get('/reports/for-updates', [ChatController::class, 'getChatReports']);


//analyses
Route::apiResource('/project-analyses', ProjectAnalysisController::class);
Route::get('/count/user/all-analyses', [ProjectAnalysisController::class, 'countAllAnalyses']); // Route for counting all analyses
Route::get('/count/user/passed-analyses', [ProjectAnalysisController::class, 'countPassedAnalyses']);// Route for counting passed analyses
Route::get('/count/user/rejected-analyses', [ProjectAnalysisController::class, 'countRejectedAnalyses']);// Route for counting rejected analyses
Route::get('/total-amount/budget-requested', [ProjectAnalysisController::class, 'countTotalAmountRequired']); // Route for counting total amount required
Route::get('/count/all-analyses', [ProjectAnalysisController::class, 'countAnalyses']);
Route::get('/count/all-analyses/passed', [ProjectAnalysisController::class, 'countAllPassedAnalyses']);
Route::get('/count/all-analyses/rejected', [ProjectAnalysisController::class, 'countAllRejectedAnalyses']);
Route::get('/count/all/total-amount-required', [ProjectAnalysisController::class, 'countAllTotalAmountRequired']);

 // Get the attendance records for the logged-in user
    Route::apiResource('/attendances', AttendanceController::class);
    Route::post('/attendance/fetch-daily-report', [AttendanceController::class, 'fetchDailyReport']);
    Route::get('/count/attendances', [AttendanceController::class, 'countAllAttendances']);

//meeting menutes
     Route::apiResource('/meeting-minutes', MeetingMinuteController::class);
     Route::post('/meeting-minutes/report', [MeetingMinuteController::class, 'fetchMeetingMinutesReport']);
     Route::get('/count/meeting-minutes', [MeetingMinuteController::class, 'countMeetingMinutes']);

//receipts
Route::resource('receipts', ReceiptController::class);
    Route::get('/my/receipts', [ReceiptController::class, 'userReceipts']);
    Route::get('/count/total-receipts', [ReceiptController::class, 'countAllReceipts']);
    Route::get('/reports/for-receipts', [ReceiptController::class, 'getReceiptsReports']);
    
   Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/yours', [ContractController::class, 'yourContracts'])->name('contracts.yours');
    Route::post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
    Route::get('/contracts/{contract_id}', [ContractController::class, 'show'])->name('contracts.show');
    Route::put('/contracts/{contract_id}', [ContractController::class, 'update'])->name('contracts.update');
    Route::delete('/contracts/{contract_id}', [ContractController::class, 'destroy'])->name('contracts.destroy');
    Route::get('/c-dropdown', [ContractController::class, 'getContractTitles']);
});
