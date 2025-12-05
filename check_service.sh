#!/bin/bash
if lsof -i :8001 > /dev/null; then
    echo "Service running on port 8001"
else
    echo "Service NOT running on port 8001"
fi
