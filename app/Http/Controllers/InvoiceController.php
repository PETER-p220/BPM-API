<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\RequestForProject;
use App\Models\Project;
use App\Models\User;
use App\Models\Tender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\Mime\Part\Multipart\Related;
use Symfony\Component\Mime\Part\Text;
use App\Models\UserLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{

   public function store(Request $request)
   {
        try {
            // Log incoming request data for debugging
            Log::info('Received request for creating invoice:', $request->all());

            // Validate incoming request data (only iscreated_by and project_id are required)
            $request->validate([
                'iscreated_by' => 'required|exists:request_for_projects,iscreated_by',  // Validate iscreated_by exists in RequestForProject
                'project_id' => 'required|exists:projects,project_id',  // Ensure project_id exists in the Project table
            ]);

            // Log validated data for debugging
            Log::info('Validated request data:', [
                'iscreated_by' => $request->iscreated_by,
                'project_id' => $request->project_id
            ]);

            // Fetch data from RequestForProject using iscreated_by and project_id with an additional condition for is_viewed
            $requestData = RequestForProject::where('iscreated_by', $request->iscreated_by)
                ->where('project_id', $request->project_id)  // Ensure project_id matches the one from the request
                ->where('is_viewed', 'approved')  // Add condition for is_viewed to be 'approved'
                ->first();  // Get the first record based on the conditions

            // Log the result of the query, ensure second argument is an array (empty array if null)
            Log::info('Fetched RequestForProject data:', $requestData ? $requestData->toArray() : []);

            // If no matching RequestForProject found, throw a custom error
            if (!$requestData) {
                return response()->json(['message' => 'RequestForProject not found for the given project_id and iscreated_by'], 404);
            }

            // Fetch project details using the project_id from the Project table
            $project = Project::where('project_id', $request->project_id)->first();  // Match projects.project_id

            // Log the result of the project query, ensure second argument is an array (empty array if null)
            Log::info('Fetched Project data:', $project ? $project->toArray() : []);

            // If project not found, throw a custom error
            if (!$project) {
                return response()->json(['message' => 'Project not found for the given project_id'], 404);
            }

            // Combine data from RequestForProject and Project tables into the Invoice model
            $invoice = new Invoice();
            $invoice->payment = $requestData->payment;
            $invoice->item = $requestData->item;
            $invoice->ref_number = $requestData->ref_number;
            $invoice->amount = $requestData->amount;
            $invoice->department_id = $requestData->department_id;
            $invoice->iscreated_by = $request->iscreated_by;  // Save iscreated_by from the request
            $invoice->description = $requestData->description;
            $invoice->project_id = $request->project_id;  // Save project_id from the request

            // Combine Project data into the Invoice
            $invoice->project_name = $project->project_name;
            $invoice->tender_id = $project->tender_id;
            $invoice->budget = $project->budget;
            $invoice->contract = $project->contract;
            $invoice->created_by = $project->created_by;
            $invoice->start_date = $project->start_date;
            $invoice->end_date = $project->end_date;

            // Save the Invoice
            $invoice->save();

            // Log the saved invoice
            Log::info('Invoice saved successfully:', $invoice->toArray());

            // Send email notification to the user about the invoice creation
            $this->sendInvoiceEmail($invoice);

            // Return the stored invoice as a response
            return response()->json([
                'message' => 'Invoice created successfully',
                'invoice' => $invoice,
            ], 201);  // Return status 201 for successful creation

        } catch (ValidationException $e) {
            // If validation fails, catch the exception and return validation errors
            Log::error('Validation error occurred:', $e->errors());
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);  // Return status 422 for validation errors

        } catch (\Exception $e) {
            // Catch any other exception and return a general error message
            Log::error('Error occurred during invoice creation:', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);  // Return status 500 for general server error
        }
    }


    // Send an email notification to the user when the invoice is created
    private function sendInvoiceEmail(Invoice $invoice)
    {
        try {
            // Fetch the user who created the invoice (iscreated_by)
            $user = User::find($invoice->iscreated_by);

            // If user not found, log an error and exit
            if (!$user) {
                Log::error('User not found for iscreated_by:', ['user_id' => $invoice->iscreated_by]);
                return;
            }

            // Prepare the email content
            $subject = 'Invoice Created: ' . $invoice->ref_number;
            $emailBody = "Dear {$user->name},\n\n"
                . "An invoice has been created with the following details:\n"
                . "Invoice Reference Number: {$invoice->ref_number}\n"
                . "Amount: {$invoice->amount}\n"
                . "Project: {$invoice->project_name}\n"
                . "Description: {$invoice->description}\n\n"
                . "Please check your portal for more details.\n\n"
                . "Thank you.";

            // Send the email
            Mail::raw($emailBody, function ($message) use ($user, $subject) {
                $message->to($user->email);
                $message->subject($subject);
            });

            // Log the successful email sending
            Log::info('Invoice email sent successfully to:', ['user_email' => $user->email]);

        } catch (\Exception $e) {
            // Log any error that occurs while sending the email
            Log::error('Error sending invoice email:', ['error' => $e->getMessage()]);
        }
    }

     // Method to fetch all invoices
    public function getAllInvoices()
{
    try {
        // Retrieve all invoices along with related user, department, and project information
        $invoices = Invoice::with([
            'user:user_id,name', // Fetch the user that created the invoice (based on iscreated_by)
            'department:department_id,name',
             'tender:tender_id,title', // Fetch the department associated with the invoice (based on department_id)
            'project:project_id,project_name' // Fetch the project details (project_name and tender_id)
        ])
        ->get();  // Fetch all invoices with the related data

        // Return the list of invoices with the additional information
        return response()->json([
            'message' => 'Invoices fetched successfully',
            'invoices' => $invoices,
        ], 200);

    } catch (\Exception $e) {
        // Log any errors
        Log::error('Error fetching invoices:', ['error' => $e->getMessage()]);

        // Return a failure response
        return response()->json([
            'message' => 'Something went wrong',
            'error' => $e->getMessage(),
        ], 500);  // Return status 500 for server errors
    }
}


public function fetchLatestInvoice()
{
    try {
        // Fetch the latest invoice based on the created_at field with the related data
        $invoice = Invoice::with([
            'department:department_id,name', // Fetch the department associated with the invoice
            'tender:tender_id,title',        // Fetch the tender associated with the invoice
            'project:project_id,project_name' // Fetch the project details
        ])
        ->select('invoices.*', 'users.name as user_name') // Select all invoice fields and fetch the user's name explicitly
        ->join('users', 'users.user_id', '=', 'invoices.iscreated_by') // Join users table on user_id = iscreated_by
        ->orderBy('invoices.created_at', 'desc') // Order by created_at to get the latest invoice
        ->first(); // Fetch the latest invoice

        // If no invoice is found, return a 404 response
        if (!$invoice) {
            return response()->json(['message' => 'No invoices found.'], 404);
        }

        // Check if 2 minutes have passed since the invoice was created
        $now = now();
        $minutesSinceCreated = $invoice->created_at->diffInMinutes($now);

        // If more than 2 minutes have passed, mark it as expired and do not fetch it
        if ($minutesSinceCreated >= 2) {
            return response()->json([
                'message' => 'Invoice expired. Please create a new invoice.',
                'isHidden' => true, // Consider expired invoices as hidden
                'isExpired' => true // Indicates that the invoice has expired
            ], 200);
        }

        // Return the invoice if it is not expired
        return response()->json([
            'invoice' => $invoice,
            'user_name' => $invoice->user_name, // Include the user's name in the response explicitly
            'isHidden' => $minutesSinceCreated >= 1, // Hidden if >= 1 minute
            'isExpired' => false // Indicates that the invoice has not expired
        ], 200);

    } catch (\Exception $e) {
        return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
    }
}




}

