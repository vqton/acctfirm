<?php
namespace Accounting\Interfaces\HTTP;

// Điều hướng request HTTP — ánh xạ URL đến Controller xử lý nghiệp vụ kế toán
class Router
{
    private array $routes = [];

    // Đăng ký route — method: GET/POST/PUT/DELETE, pattern: URL với :param, handler: closure
    // pattern hỗ trợ tham số động: /api/users/:id → match /api/users/123
    // handler là closure được gọi khi route match — nhận tham số từ URL làm đối số
    public function addRoute(string $method, string $pattern, $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler
        ];
    }

    // Điều phối request đến xử lý — tìm route phù hợp, gọi handler tương ứng
    // Nếu không tìm thấy route, trả về 404
    // Cơ chế: Duyệt danh sách route → so khớp method + pattern (regex) → gọi handler
    // Pattern :param được chuyển thành regex group ([^/]+) — bắt toàn bộ giá trị
    // Xử lý URI: bỏ query string, chuẩn hóa trailing slash (/abc/ → /abc)
    // RỦI RO: Nếu có 2 route match cùng URI, route nào đăng ký trước được chạy trước
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

    public function get(string $pattern, $handler): void { $this->addRoute('GET', $pattern, $handler); }
    public function post(string $pattern, $handler): void { $this->addRoute('POST', $pattern, $handler); }
    public function put(string $pattern, $handler): void { $this->addRoute('PUT', $pattern, $handler); }
    public function delete(string $pattern, $handler): void { $this->addRoute('DELETE', $pattern, $handler); }
}