# OpenRouter Direct API Test Script (PowerShell)
$OPENROUTER_API_KEY = "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"

$body = @{
    model = "openrouter/free"
    messages = @(
        @{
            role = "user"
            content = @(
                @{
                    type = "text"
                    text = "What is in this image?"
                },
                @{
                    type = "image_url"
                    image_url = @{
                        url = "https://live.staticflickr.com/3851/14825276609_098cac593d_b.jpg"
                    }
                }
            )
        }
    )
} | ConvertTo-Json -Depth 10

Invoke-RestMethod -Uri "https://openrouter.ai/api/v1/chat/completions" `
    -Method Post `
    -Headers @{
        "Authorization" = "Bearer $OPENROUTER_API_KEY"
        "Content-Type" = "application/json"
    } `
    -Body $body
