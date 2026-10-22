<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCmsTools\Test\Api\Security;

use Magebit\Mcp\Model\JsonRpc\ErrorCode;
use Magebit\Mcp\Test\Api\McpTestCase;
use Magebit\Mcp\Test\Api\Security\AuditLogReader;
use Magebit\Mcp\Test\Api\Security\ToolPayload;
use Magebit\Mcp\Test\Api\TokenFixture;

/**
 * A token for an admin whose role only allows CMS tools cannot reach anything else,
 * and a token scope can narrow it further.
 *
 * @magentoApiDataFixture Magebit_Mcp::Test/Api/_files/cms_only_admin.php
 */
class CmsOnlyRoleTest extends McpTestCase
{
    /** This test mints its own token for `cmsAdmin`. */
    protected ?bool $issueToken = false;

    /**
     * @return void
     */
    public function testToolsListContainsOnlyCmsReadAndWhoami(): void
    {
        $this->useCmsToken();

        $names = ToolPayload::toolNames($this->toolsList());
        sort($names);

        // Create tools are hidden because the token has allow_writes = false.
        self::assertSame(
            ['cms_block_get', 'cms_block_list', 'cms_page_get', 'cms_page_list', 'system_whoami'],
            $names
        );
    }

    /**
     * @return void
     */
    public function testCallingOrderListIsForbiddenAndAudited(): void
    {
        $this->useCmsToken();

        $response = $this->toolsCall('sales.order.list');

        $this->assertJsonRpcError($response, ErrorCode::FORBIDDEN->value, 200);
        $audit = AuditLogReader::latest('sales.order.list', $this->tokenRowId);
        self::assertNotNull($audit, 'Expected an audit row for the refused call.');
        self::assertSame('-32004', $audit['error_code'] ?? null);
    }

    /**
     * @return void
     */
    public function testCallingSystemStoreListIsForbidden(): void
    {
        $this->useCmsToken();

        $this->assertJsonRpcError($this->toolsCall('system.store.list'), ErrorCode::FORBIDDEN->value, 200);
    }

    /**
     * @return void
     */
    public function testWriteToolOutsideRoleIsForbiddenNotWriteGated(): void
    {
        $this->useCmsToken();

        // The role check runs before the write gate, so this is -32004 and not -32012.
        $response = $this->toolsCall('cms.page.delete', ['id' => 1]);

        $this->assertJsonRpcError($response, ErrorCode::FORBIDDEN->value, 200);
    }

    /**
     * @return void
     */
    public function testTokenScopeNarrowsBelowRole(): void
    {
        $this->useCmsToken(['cms.page.list']);

        $this->assertJsonRpcError($this->toolsCall('cms.block.list'), ErrorCode::FORBIDDEN->value, 200);
        $this->assertJsonRpcSuccess($this->toolsCall('cms.page.list'));
    }

    /**
     * Issue a read-only token for `cmsAdmin`; the base tearDown() deletes it.
     *
     * @param array<int, string> $scope
     * @return void
     */
    private function useCmsToken(array $scope = []): void
    {
        $issued = TokenFixture::issueForAdmin('cmsAdmin', false, null, $scope);
        $this->bearerToken = $issued['token'];
        $this->tokenRowId = $issued['id'];
    }
}
