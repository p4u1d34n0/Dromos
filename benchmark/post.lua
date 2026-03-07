wrk.method = "POST"
wrk.headers["Content-Type"] = "application/json"

local counter = 0

request = function()
    counter = counter + 1
    local body = string.format(
        '{"name":"User %d","email":"user%d@bench.test"}',
        counter, counter
    )
    return wrk.format(nil, nil, nil, body)
end
