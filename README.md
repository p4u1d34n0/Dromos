
Summary of How It Works
Route Definition: You define a route with placeholders using curly braces (e.g., /data/{id}/user/{user_id}).
Parameter Extraction: The RequestParameters::handle method extracts parameter names and their corresponding values from the actual URL.
Reconstruction: The expected URL is reconstructed by replacing the placeholders with actual values.
Matching: The router checks if the reconstructed expected URL matches the actual URL.
Execution: If a match is found, the corresponding target (closure or controller method) is called with the extracted parameters.