/**
 * Website Tailors — Static Website Configuration
 * 
 * Configure the URL of your separate Website Tailors PHP backend server.
 * 
 * PRODUCTION EXAMPLE:
 *   API_BASE_URL: "https://websitetailors.com"
 * 
 * LOCAL DEVELOPMENT EXAMPLE:
 *   API_BASE_URL: "http://127.0.0.1:8088"
 * 
 * When left empty (""), requests will use the relative path "/api/leads/create.php".
 */
window.WebsiteTailors_CONFIG = {
  // Configurable PHP backend API URL:
  // For production Netlify deployment: set to e.g. "https://websitetailors.com" or relative ""
  API_BASE_URL: ""
};
