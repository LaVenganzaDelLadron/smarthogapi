#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// WiFi Credentials
const char* ssid = "YOUR_SSID";
const char* password = "YOUR_PASSWORD";

// Server Configuration
const char* serverUrl = "http://192.168.1.100:8000/api/v1";
const char* apiToken = "YOUR_SANCTUM_TOKEN"; // Generate via Laravel API

// GPIO Pin Assignments (Relay Module)
const int RELAY_STARTER = 12;
const int RELAY_GROWER = 14;
const int RELAY_FINISHER = 27;
const int RELAY_MAINTENANCE = 26;

// Feeder Configuration
const int FEEDER_ID = 1;
const int POLL_INTERVAL_MS = 10000; // Poll every 10 seconds

// Global Variables
unsigned long lastPollTime = 0;
bool isConnected = false;

struct RelayConfig {
  int pin;
  int maxDuration;
};

struct FeedingJob {
  int id;
  int relayPin;
  int maxDuration;
  String feedType;
};

void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("\n\nESP32 Feeding System Starting...");

  // Initialize relay pins as outputs
  pinMode(RELAY_STARTER, OUTPUT);
  pinMode(RELAY_GROWER, OUTPUT);
  pinMode(RELAY_FINISHER, OUTPUT);
  pinMode(RELAY_MAINTENANCE, OUTPUT);

  // Ensure all relays are OFF initially
  digitalWrite(RELAY_STARTER, LOW);
  digitalWrite(RELAY_GROWER, LOW);
  digitalWrite(RELAY_FINISHER, LOW);
  digitalWrite(RELAY_MAINTENANCE, LOW);

  // Connect to WiFi
  connectToWiFi();
}

void loop() {
  // Check WiFi connection
  if (WiFi.status() != WL_CONNECTED) {
    connectToWiFi();
    delay(5000);
    return;
  }

  // Poll for new jobs every POLL_INTERVAL_MS
  if (millis() - lastPollTime >= POLL_INTERVAL_MS) {
    lastPollTime = millis();
    pollForJobs();
  }

  delay(100);
}

/**
 * Connect to WiFi network
 */
void connectToWiFi() {
  Serial.print("Connecting to WiFi: ");
  Serial.println(ssid);

  WiFi.begin(ssid, password);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✓ WiFi Connected!");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
    isConnected = true;
  } else {
    Serial.println("\n✗ WiFi Connection Failed");
    isConnected = false;
  }
}

/**
 * Poll server for next feeding job
 */
void pollForJobs() {
  if (!isConnected) {
    Serial.println("Not connected to WiFi, skipping poll");
    return;
  }

  HTTPClient http;
  String url = String(serverUrl) + "/feeding-queue/next-job";

  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Authorization", String("Bearer ") + apiToken);

  // Create request payload
  StaticJsonDocument<200> requestDoc;
  requestDoc["feeder_id"] = FEEDER_ID;
  requestDoc["max_jobs"] = 1;

  String requestBody;
  serializeJson(requestDoc, requestBody);

  Serial.print("Polling for jobs... ");

  int httpCode = http.POST(requestBody);

  if (httpCode == 200) {
    String response = http.getString();
    Serial.println("✓ Response received");

    // Parse response
    StaticJsonDocument<512> responseDoc;
    DeserializationError error = deserializeJson(responseDoc, response);

    if (!error) {
      if (responseDoc["count"] > 0) {
        JsonArray jobs = responseDoc["jobs"].as<JsonArray>();

        for (JsonObject job : jobs) {
          Serial.println("\n--- New Feeding Job ---");
          Serial.print("Job ID: ");
          Serial.println(job["id"].as<int>());
          Serial.print("Feed Type: ");
          Serial.println(job["feed_type"].as<const char*>());
          Serial.print("Relay Pin: ");
          Serial.println(job["relay_pin"].as<int>());
          Serial.print("Max Duration: ");
          Serial.println(job["max_duration_seconds"].as<int>());

          // Execute the feeding job
          executeFeedingJob(
            job["id"].as<int>(),
            job["relay_pin"].as<int>(),
            job["max_duration_seconds"].as<int>(),
            job["feed_type"].as<String>()
          );
        }
      } else {
        Serial.println("No jobs pending");
      }
    } else {
      Serial.print("JSON parse error: ");
      Serial.println(error.c_str());
    }
  } else {
    Serial.print("✗ HTTP Error: ");
    Serial.println(httpCode);
  }

  http.end();
}

/**
 * Execute a feeding job
 */
void executeFeedingJob(int jobId, int relayPin, int maxDuration, String feedType) {
  Serial.println("\n>>> Executing Feeding Job <<<");

  // Mark as processing
  updateJobStatus(jobId, "processing");

  // Activate relay (GPIO pin HIGH = Relay ON)
  Serial.print("Activating relay on GPIO ");
  Serial.println(relayPin);
  digitalWrite(relayPin, HIGH);

  unsigned long startTime = millis();

  // Run motor for duration (with safety timeout)
  while (millis() - startTime < (maxDuration * 1000)) {
    // Optional: Monitor for emergency stop conditions
    // if (someErrorCondition) break;

    delay(100);
  }

  // Deactivate relay (GPIO pin LOW = Relay OFF)
  digitalWrite(relayPin, LOW);
  Serial.println("Relay deactivated");

  unsigned long actualDuration = millis() - startTime;
  int durationSeconds = actualDuration / 1000;

  // Report completion to server
  updateJobStatus(jobId, "completed", durationSeconds);

  Serial.println(">>> Job Completed <<<\n");
}

/**
 * Update job status on server
 */
void updateJobStatus(int jobId, String status, int duration = 0) {
  HTTPClient http;
  String url = String(serverUrl) + "/feeding-queue/" + jobId;

  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Authorization", String("Bearer ") + apiToken);
  http.setConnectTimeout(3000);
  http.setTimeout(3000);

  // Create request payload
  StaticJsonDocument<200> requestDoc;
  requestDoc["status"] = status;

  if (status == "completed") {
    requestDoc["duration_seconds"] = duration;
    requestDoc["actual_feed_time"] = getCurrentTimestamp();
    requestDoc["amount_dispensed"] = 2.5; // Fixed amount (could be calculated)
  }

  if (status == "error") {
    requestDoc["error_message"] = "Relay timeout exceeded";
    requestDoc["duration_seconds"] = duration;
  }

  String requestBody;
  serializeJson(requestDoc, requestBody);

  Serial.print("Updating job status to '");
  Serial.print(status);
  Serial.print("'... ");

  int httpCode = http.PATCH(requestBody);

  if (httpCode == 200) {
    Serial.println("✓ Status updated");
  } else {
    Serial.print("✗ Error: ");
    Serial.println(httpCode);
  }

  http.end();
}

/**
 * Get relay configuration from server
 * Call this on startup to cache relay configs
 */
void fetchRelayConfig() {
  if (!isConnected) return;

  HTTPClient http;
  String url = String(serverUrl) + "/feeders/" + FEEDER_ID + "/relay-config";

  http.begin(url);
  http.addHeader("Authorization", String("Bearer ") + apiToken);

  Serial.println("Fetching relay configuration...");

  int httpCode = http.GET();

  if (httpCode == 200) {
    String response = http.getString();
    Serial.println("✓ Configuration received:");
    Serial.println(response);

    // Parse and cache configuration
    StaticJsonDocument<512> doc;
    deserializeJson(doc, response);

    JsonArray relays = doc["relays"].as<JsonArray>();
    for (JsonObject relay : relays) {
      Serial.print("  Feed Type: ");
      Serial.print(relay["feed_type"].as<const char*>());
      Serial.print(" → GPIO ");
      Serial.print(relay["relay_pin"].as<int>());
      Serial.print(" (Max ");
      Serial.print(relay["max_duration_seconds"].as<int>());
      Serial.println("s)");
    }
  } else {
    Serial.print("✗ Failed to fetch config: ");
    Serial.println(httpCode);
  }

  http.end();
}

/**
 * Generate ISO 8601 timestamp
 * Format: 2026-05-02T10:15:30Z
 */
String getCurrentTimestamp() {
  // Note: This requires time sync from NTP server
  // For now, return placeholder
  return "2026-05-02T10:15:30Z";
}

/**
 * Diagnostic: Print relay pin mapping
 */
void printRelayMap() {
  Serial.println("\n=== Relay Pin Mapping ===");
  Serial.println("GPIO 12 → Starter Feed");
  Serial.println("GPIO 14 → Grower Feed");
  Serial.println("GPIO 27 → Finisher Feed");
  Serial.println("GPIO 26 → Maintenance Feed");
  Serial.println("========================\n");
}
