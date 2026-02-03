// firebase-config.js
import { initializeApp } from "https://www.gstatic.com/firebasejs/11.9.1/firebase-app.js";
import { getAnalytics } from "https://www.gstatic.com/firebasejs/11.9.1/firebase-analytics.js";
import { getDatabase } from "https://www.gstatic.com/firebasejs/11.9.1/firebase-database.js";

const firebaseConfig = {
  apiKey: "AIzaSyDYuxviIf0uHsi6JZ6ORaQz40eXSiiM1Xk",
  authDomain: "vip-admin-panel.firebaseapp.com",
  databaseURL: "https://vip-admin-panel-default-rtdb.firebaseio.com",
  projectId: "vip-admin-panel",
  storageBucket: "vip-admin-panel.firebasestorage.app",
  messagingSenderId: "755788106751",
  appId: "1:755788106751:web:8065602798b061e5dbb5ca"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);
const analytics = getAnalytics(app);
const db = getDatabase(app);

// Export the initialized services
export { app, analytics, db };