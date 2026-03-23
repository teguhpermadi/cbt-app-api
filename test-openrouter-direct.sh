#!/bin/bash

# OpenRouter Direct API Test Script (Bash/Git Bash)
OPENROUTER_API_KEY="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"

curl https://openrouter.ai/api/v1/chat/completions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $OPENROUTER_API_KEY" \
  -d '{
  "model": "openrouter/free",
  "messages": [
      {
        "role": "user",
        "content": [
          {
            "type": "text",
            "text": "What is in this image?"
          },
          {
            "type": "image_url",
            "image_url": {
              "url": "https://live.staticflickr.com/3851/14825276609_098cac593d_b.jpg"
            }
          }
        ]
      }
    ]
}'
