<?php
/**
 * Gemini AI Helper - Shanfix Technology
 * Handles integration with Google Gemini for smart WhatsApp fallbacks.
 */
class Gemini_AI {
    private $apiKey;
    private $model;
    private $systemPrompt;

    public function __construct($apiKey, $model = 'gemini-1.5-flash', $systemPrompt = '') {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->systemPrompt = $systemPrompt ?: "You are a helpful assistant for a business on WhatsApp. Keep your responses concise and friendly.";
    }

    /**
     * Generate a response for a user message
     */
    public function generateResponse($userMessage) {
        if (empty($this->apiKey)) {
            return "AI Error: API Key missing.";
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            "contents" => [
                [
                    "role" => "user",
                    "parts" => [
                        ["text" => "System Instruction: " . $this->systemPrompt . "\n\nUser Message: " . $userMessage]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.7,
                "topK" => 40,
                "topP" => 0.95,
                "maxOutputTokens" => 1024,
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            error_log("Gemini AI Error: " . ($error['error']['message'] ?? 'Unknown error'));
            return null;
        }

        $data = json_decode($response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        return $text ? trim($text) : null;
    }
}
