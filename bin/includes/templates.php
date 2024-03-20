<?php
define('TEMPLATES', [
    'component' => `<?php
    /**
     * Example component {TEMPLATE_NAME}
     * @var string \$COMPONENT_ID
     * @var string \$example_prop
     */
    ?>
    <div id="<?= \$COMPONENT_ID ?>">
        <h1>Example component</h1>
        <p>Example prop: <?= \$example_prop ?></p>
    </div>`,
    'page' => `<?php
    /**
     * Example page {TEMPLATE_NAME}
     * @var AnzeBlaBla\Simplite\Application \$app
     */
    
     \$app->debug("Example debug message");
    
    ?>
    <h1>Example page</h1>
    
    <p>URI: <?= \$app->request->uri ?></p>`,
    'api' => `<?php
    /**
     * Example API route {TEMPLATE_NAME}
     */
    
    use AnzeBlaBla\Simplite\Application;
    
    return [
        "GET" => function (Application \$app) {
            if (!isset(\$app->request->query['test'])) {
                http_response_code(400);
                return [
                    "success" => false,
                    "message" => "Missing required query parameter: test"
                ];
            }
            return [
                "success" => true,
                "message" => "Hello world!",
            ];
        },
        "POST" => function (Application \$app) {
            if (!\$app->request->hasBody(['test'])) {
                http_response_code(400);
                return [
                    "success" => false,
                    "message" => "Missing required body parameter: test or test2"
                ];
            }
            return [
                "success" => true,
                "message" => "Hello world!",
            ];
        }
    ];`,
    'layout' => `<?php
    /**
     * Example layout {TEMPLATE_NAME}
     * @var string \$content
     */
    ?>
    <div>
        <?= \$content ?>
    </div>`
]);


