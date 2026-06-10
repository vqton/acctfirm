<?php
namespace Accounting\Interfaces\HTTP;

/**
 * MODULE: Điều hướng Request HTTP (Router)
 *
 * Ánh xạ URL đến Controller xử lý nghiệp vụ kế toán.
 * Hỗ trợ tham số động trong URL pattern: /api/users/:id -> match /api/users/123
 *
 * API endpoints:
 *   (Đây là Router engine — không phải controller)
 *
 * Rủi ro:
 *   - Nếu có 2 route match cùng URI, route nào đăng ký trước được chạy trước
 *   - Pattern :param bắt toàn bộ giá trị ([^/]+) — không hỗ trợ nested params
 *   - URI được chuẩn hóa bỏ query string và trailing slash
 */
class Router
{
    private array $routes = [];

    /**
     * Đăng ký route — method: GET/POST/PUT/DELETE, pattern: URL với :param, handler: closure
     *
     * @param string $method HTTP method
     * @param string $pattern URL pattern hỗ trợ :param
     * @param callable $handler Closure xử lý khi route match
     * @return void
     */
    public function addRoute(string $method, string $pattern, $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler
        ];
    }

    /**
     * Điều phối request đến xử lý — tìm route phù hợp, gọi handler tương ứng
     *
     * Duyệt danh sách route -> so khớp method + pattern (regex) -> gọi handler.
     * Pattern :param được chuyển thành regex group ([^/]+).
     * URI: bỏ query string, chuẩn hóa trailing slash.
     * Nếu không tìm thấy route, trả về 404.
     *
     * @return void
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = explode('?', $uri)[0];

        if ($uri !== '/' && substr($uri, -1) === '/') {
            $uri = substr($uri, 0, -1);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $parts = preg_split('/:([a-zA-Z0-9_]+)/', $route['pattern'], -1, PREG_SPLIT_DELIM_CAPTURE);
            $regex = '';
            foreach ($parts as $i => $part) {
                $regex .= $i % 2 === 0 ? preg_quote($part, '~') : '([^/]+)';
            }
            $regex = '~^' . $regex . '$~';

            if (preg_match($regex, $uri, $matches)) {
                array_shift($matches);
                call_user_func_array($route['handler'], $matches);
                return;
            }
        }

        HttpError::notFound('Không tìm thấy đường dẫn: ' . $method . ' ' . $uri);
    }

    /**
     * Đăng ký route GET
     *
     * @param string $pattern URL pattern
     * @param callable $handler Closure xử lý
     * @return void
     */
    public function get(string $pattern, $handler): void { $this->addRoute('GET', $pattern, $handler); }

    /**
     * Đăng ký route POST
     *
     * @param string $pattern URL pattern
     * @param callable $handler Closure xử lý
     * @return void
     */
    public function post(string $pattern, $handler): void { $this->addRoute('POST', $pattern, $handler); }

    /**
     * Đăng ký route PUT
     *
     * @param string $pattern URL pattern
     * @param callable $handler Closure xử lý
     * @return void
     */
    public function put(string $pattern, $handler): void { $this->addRoute('PUT', $pattern, $handler); }

    /**
     * Đăng ký route DELETE
     *
     * @param string $pattern URL pattern
     * @param callable $handler Closure xử lý
     * @return void
     */
    public function delete(string $pattern, $handler): void { $this->addRoute('DELETE', $pattern, $handler); }
}
