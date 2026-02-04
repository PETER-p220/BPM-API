<template>
  <div class="p-4 dashboard" style="margin-top: 40px;color:white;max-height: 550px; overflow-y: auto;font-family: 'Times New Roman', serif;font-size: 14px ;font-style:oblique">
    <div class="flex flex-wrap -mx-4">

    

<!-- Users & Departments Card -->
<div class="w-full px-4 mb-4 sm:w-1/2 lg:w-1/4">
  <div class="h-full p-6 bg-gradient-to-r from-[#1f618d] to-[#2980b9] rounded-2xl shadow-2xl text-white transform transition-transform duration-300 hover:scale-105">
    <div class="flex items-center gap-4 mb-4">
      <i class="text-5xl text-yellow-300 fas fa-users-cog"></i>
      <h2 class="text-xl font-extrabold">Users & Departments</h2>
    </div>
    <div class="space-y-2">
      <div class="flex justify-between items-center">
        <span class="text-lg">👥 User Roles:</span>
        <span class="font-semibold text-xl">{{ totalRoles }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-lg">🙋 Users:</span>
        <span class="font-semibold text-xl">{{ totalUsers }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-lg">🏢 Departments:</span>
        <span class="font-semibold text-xl">{{ totalDepartments }}</span>
      </div>
    </div>
  </div>
</div>



      
     <!-- Tenders Card -->
<div class="w-full px-4 mb-4 sm:w-1/2 lg:w-1/4">
  <div class="h-full p-6 bg-gradient-to-r from-[#6e2c00] to-[#a04000] rounded-2xl shadow-2xl text-white transform transition-transform duration-300 hover:scale-105">
    <div class="flex items-center gap-4 mb-4">
      <i class="text-5xl text-yellow-400 fas fa-file-contract"></i>
      <h2 class="text-xl font-extrabold">Tenders</h2>
    </div>
    <div class="space-y-2">
      <div class="flex justify-between items-center">
        <span class="text-lg">📌 Registered:</span>
        <span class="font-semibold text-xl">{{ totalTenders }}</span>
      </div>
      <div class="flex justify-between items-center">
  <span class="text-lg">📑 Assigned :</span>
  <span class="font-semibold text-xl">{{ totalAssignedTenders }}</span>
</div>

      <div class="flex justify-between items-center">
        <span class="text-lg">📑 Submitted:</span>
        <span class="font-semibold text-xl">{{ totalTenderSubmissions }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-lg">⚙️ In Progress:</span>
        <span class="font-semibold text-xl">{{ totalTenders }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-lg">⏳ Deadline Reached:</span>
        <span class="font-semibold text-xl">{{ totalTenders }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-lg">❌ Failed:</span>
        <span class="font-semibold text-xl">{{ totalFailedTenders  }}</span>
      </div>
    </div>
  </div>
</div>


<!-- Project Analyses Card -->
<div class="w-full px-4 mb-4 sm:w-1/2 lg:w-1/4">
  <div class="h-full p-6 bg-gradient-to-r from-[#0e6251] to-[#1abc9c] rounded-2xl shadow-2xl text-white transform transition-transform duration-300 hover:scale-105">
    <div class="flex items-center gap-4 mb-4">
      <i class="text-5xl text-yellow-300 fas fa-chart-line"></i>
      <h2 class="text-xl font-extrabold">Project Analyses</h2>
    </div>
    <div class="space-y-2">
      <div class="flex justify-between items-center">
        <span class="text-lg">📥 Incoming:</span>
        <span class="font-semibold text-xl">{{ totalPricesSchedules }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-lg">✅ Approved:</span>
        <span class="font-semibold text-xl">{{ totalPricesSchedules }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-lg">❌ Rejected:</span>
        <span class="font-semibold text-xl">{{ totalPricesSchedules }}</span>
      </div>
    </div>
  </div>
</div>




<!-- Price Schedules Card -->
<div class="w-full px-4 mb-4 sm:w-1/2 lg:w-1/4">
  <div class="h-full p-6 bg-gradient-to-r from-[#CD5C5C] to-[#CD5C5C] rounded-2xl shadow-2xl text-white transform transition-transform duration-300 hover:scale-105">
    <div class="flex items-center gap-4 mb-4">
      <i class="text-5xl text-yellow-300 fas fa-dollar-sign"></i>
      <h2 class="text-xl font-extrabold">Price Schedules</h2>
    </div>
    <div class="space-y-2">
      <div class="flex justify-between items-center">
        <span class="text-lg">📥 Incoming:</span>
        <span class="font-semibold text-xl">{{ totalPricesSchedules }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-lg">✅ Approved:</span>
        <span class="font-semibold text-xl">{{ totalPricesSchedules }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-lg">❌ Rejected:</span>
        <span class="font-semibold text-xl">{{ totalPricesSchedules }}</span>
      </div>
    </div>
  </div>
</div>


  
        <!-- Project Requests Card -->
<div class="w-full px-4 mb-4 sm:w-1/2 lg:w-1/4">
  <div class="h-full p-6 bg-gradient-to-r from-[#9a7d0a] to-[#d4ac0d] rounded-2xl shadow-2xl text-white transform transition-transform duration-300 hover:scale-105">
    <div class="flex items-center gap-4 mb-4">
      <i class="text-5xl text-yellow-300 fas fa-project-diagram"></i>
      <h2 class="text-3xl font-extrabold">Project Requests</h2>
    </div>
    <div class="space-y-2">
      <div class="flex justify-between items-center">
        <span class="text-lg">📊 Total Projects:</span>
        <span class="font-semibold text-xl">{{ totalProjects }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-lg">⚙️ On Progress:</span>
        <span class="font-semibold text-xl">{{ totalOnProgressProjects }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-lg">✅ Completed:</span>
        <span class="font-semibold text-xl">{{ totalCompletedProjects }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-lg">❌ Failed:</span>
        <span class="font-semibold text-xl">{{ totalFailedProjects }}</span>
      </div>
    </div>
  </div>
</div>


  <!-- Project  Card -->
  <div class="w-full px-4 mb-4 sm:w-1/2 lg:w-1/4">
  <div class="h-full p-6 bg-[#186a3b] rounded-2xl shadow-2xl text-white transform transition-transform duration-300 hover:scale-105">
    <div class="flex items-center gap-4 mb-4">
      <i class="text-5xl text-yellow-300 fas fa-project-diagram"></i>
      <h2 class="text-3xl font-extrabold">Projects</h2>
    </div>
    <div class="space-y-2">
      <div class="flex justify-between items-center">
        <span class="text-lg">📊 Total Projects:</span>
        <span class="font-semibold text-xl">{{ totalProjects }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-lg">⚙️ On Progress:</span>
        <span class="font-semibold text-xl">{{ totalOnProgressProjects }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-lg">✅ Completed:</span>
        <span class="font-semibold text-xl">{{ totalCompletedProjects }}</span>
      </div>
      <div class="flex justify-between items-center">
        <span class="text-lg">❌ Failed:</span>
        <span class="font-semibold text-xl">{{ totalFailedProjects }}</span>
      </div>
    </div>
  </div>
</div>
  
    </div>

  </div>
  <div class="text-center">
    <div class="text-center mt-6">
       <!-- Apex Chart Container -->
       <div id="apex-multiple-column-charts" class="w-full px-4">
        <apexchart type="line" :options="chartOptions" :series="chartSeries" height="400" />
      </div>
    </div>
    <div>
   
  </div>
</div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from '@/axios'; // Ensure this points to your axios instance
import VueApexCharts from "vue3-apexcharts";

// Define the reactive variables to hold the data from the API responses
const totalUsers = ref(0);


// Function to fetch the total users count
const fetchTotalUsers = async () => {
  try {
    const response = await axios.get('api/count/users');
    totalUsers.value = response.data.total_users;
  } catch (error) {
    console.error('Error fetching total users:', error);
  }
};

// Function to fetch the total roles count
const fetchTotalRoles = async () => {
  try {
    const response = await axios.get('api/count/roles');
    totalRoles.value = response.data.user_roles;
  } catch (error) {
    console.error('Error fetching total roles:', error);
  }
};

// Function to fetch the total departments count
const fetchTotalDepartments = async () => {
  try {
    const response = await axios.get('api/count/departments');
    totalDepartments.value = response.data.total_departments;
  } catch (error) {
    console.error('Error fetching total departments:', error);
  }
};

// Function to fetch the total tenders count
const fetchTotalTenders = async () => {
  try {
    const response = await axios.get('api/count/registered-tenders');
    totalTenders.value = response.data.registered_tenders;
  } catch (error) {
    console.error('Error fetching total tenders:', error);
  }
};

// Function to fetch the total assigned tenders count
const fetchTotalAssignedTenders = async () => {
  try {
    const response = await axios.get('api/count/assigned-tenders');
    totalAssignedTenders.value = response.data.count_assigned_tenders;
  } catch (error) {
    console.error('Error fetching assigned tenders:', error);
  }
};

// Function to fetch the total tender submissions count
const fetchTotalTenderSubmissions = async () => {
  try {
    const response = await axios.get('api/count/tenders-submissions');
    totalTenderSubmissions.value = response.data.submitted_tenders;
  } catch (error) {
    console.error('Error fetching tender submissions:', error);
  }
};

// Function to fetch the total published tenders count
const fetchTotalPublishedTenders = async () => {
  try {
    const response = await axios.get('api/count/published-tenders');
    totalPublishedTenders.value = response.data.count_published_tenders;
  } catch (error) {
    console.error('Error fetching published tenders:', error);
  }
};

// Function to fetch the total awarded tenders count
const fetchTotalAwardedTenders = async () => {
  try {
    const response = await axios.get('api/count/awarded-tenders');
    totalAwardedTenders.value = response.data.count_awarded_tenders;
  } catch (error) {
    console.error('Error fetching awarded tenders:', error);
  }
};

// Function to fetch the total failed tenders count
const fetchTotalFailedTenders = async () => {
  try {
    const response = await axios.get('api/count/failed-tenders');
    totalFailedTenders.value = response.data.count_failed_tenders;
  } catch (error) {
    console.error('Error fetching failed tenders:', error);
  }
};

// Function to fetch the total requests count
const fetchTotalRequests = async () => {
  try {
    const response = await axios.get('api/count/requests');
    totalRequests.value = response.data.totalRequests;
  } catch (error) {
    console.error('Error fetching total requests:', error);
  }
};

// Function to fetch the total projects count
const fetchTotalProjects = async () => {
  try {
    const response = await axios.get('api/count/total-projects');
    totalProjects.value = response.data.count_total_projects;
  } catch (error) {
    console.error('Error fetching total projects:', error);
  }
};

// Function to fetch the total failed projects count
const fetchTotalFailedProjects = async () => {
  try {
    const response = await axios.get('api/count/failed-projects');
    totalFailedProjects.value = response.data.total_failed_projects;
  } catch (error) {
    console.error('Error fetching failed projects:', error);
  }
};

// Function to fetch the total completed projects count
const fetchTotalCompletedProjects = async () => {
  try {
    const response = await axios.get('api/count/completed-projects');
    totalCompletedProjects.value = response.data.total_completed_projects;
  } catch (error) {
    console.error('Error fetching completed projects:', error);
  }
};

// Function to fetch the total project activities count
const fetchTotalProjectActivities = async () => {
  try {
    const response = await axios.get('api/count/project-activities');
    totalProjectActivities.value = response.data.activity_count;
  } catch (error) {
    console.error('Error fetching project activities:', error);
  }
};

// Function to fetch the total budget
const fetchTotalBudget = async () => {
  try {
    const response = await axios.get('api/count/total-budget');
    totalBudget.value = response.data.data.total_budget;
  } catch (error) {
    console.error('Error fetching total budget:', error);
  }
};

// Function to fetch the total receipts count
const fetchTotalReceipts = async () => {
  try {
    const response = await axios.get('api/count/total-receipts');
    totalReceipts.value = response.data.data.total_receipts;
  } catch (error) {
    console.error('Error fetching total receipts:', error);
  }
};

// Function to fetch the total chats count
const fetchTotalChats = async () => {
  try {
    const response = await axios.get('api/count/total-updates');
    totalChats.value = response.data.data.updates_count;
  } catch (error) {
    console.error('Error fetching total chats:', error);
  }
};

// Function to fetch the total attendance count
const fetchTotalAttendances = async () => {
  try {
    const response = await axios.get('api/count/attendances');
    totalAttendances.value = response.data.data.total_attendances;
  } catch (error) {
    console.error('Error fetching total attendances:', error);
  }
};

// Function to fetch the total meeting minutes count
const fetchTotalMeetingMinutes = async () => {
  try {
    const response = await axios.get('api/count/meeting-minutes');
    totalMeetingMinutes.value = response.data.total_meeting_minutes;
  } catch (error) {
    console.error('Error fetching meeting minutes:', error);
  }
};

// Call all fetch functions when the component is mounted
onMounted(() => {
  fetchTotalUsers();
  fetchTotalRoles();
  fetchTotalDepartments();
  fetchTotalTenders();
  fetchTotalAssignedTenders();
  fetchTotalTenderSubmissions();
  fetchTotalPublishedTenders();
  fetchTotalAwardedTenders();
  fetchTotalFailedTenders();
  fetchTotalRequests();
  fetchTotalProjects();
  fetchTotalFailedProjects();
  fetchTotalCompletedProjects();
  fetchTotalProjectActivities();
  fetchTotalBudget();
  fetchTotalReceipts();
  fetchTotalChats();
  fetchTotalAttendances();
  fetchTotalMeetingMinutes();
});
</script>

<style scoped>
/* Additional styles if needed */
#apex-multiple-column-charts {
  margin-top: 20px;
}
</style>





