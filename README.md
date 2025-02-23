# Simple PHP CORS Proxy

A simple PHP-based CORS proxy server that allows you to bypass CORS restrictions by forwarding HTTP requests to a target URL and adding necessary CORS headers to the response.

## Functionality

1. **`clean_url($url)`**:
   - **Purpose**: This function cleans and validates the provided URL.
   - **Description**: It trims the URL and checks if it’s a valid URL using `filter_var` with `FILTER_VALIDATE_URL`. If the URL is invalid, it returns `false`.

2. **CORS Headers**:
   - **Purpose**: Automatically adds CORS headers to the response.
   - **Description**: The script sends the header `Access-Control-Allow-Origin: *`, allowing cross-origin requests. It also includes the `X-Forwarded-For` header to pass the client's IP address.

3. **cURL Request**:
   - **Purpose**: The script uses cURL to forward the request to the target URL and fetch the response.
   - **Description**: It initializes a cURL session, sets various options (like URL, return transfer, and follow location), and executes the request to fetch the response from the target URL. If any cURL error occurs, it is displayed.

4. **Response Handling**:
   - **Purpose**: The response from the cURL request is returned to the client.
   - **Description**: After cURL fetches the response, the content is sent back to the client, along with the CORS headers, effectively bypassing any CORS restrictions on the target server.

## Usage

- The script expects a URL as part of the request path (e.g., `corsproxy.php/target-url`).
- It then validates and cleans the target URL, forwards the request to that URL, and returns the response with appropriate CORS headers.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for more details.
