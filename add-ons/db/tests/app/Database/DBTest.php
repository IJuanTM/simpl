<?php

declare(strict_types=1);

namespace tests\Database;

use app\Database\DB;
use PDOException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * DB's query-building is a set of pure private static methods, reachable via reflection.
 * Only execute()/connect() touch a real PDO, so none of this needs a database.
 */
class DBTest extends TestCase
{
    public function testSanitizeAcceptsAPlainIdentifier(): void
    {
        $this->assertSame('users', $this->call('sanitize', ['users']));
    }

    private function call(string $method, array $args): mixed
    {
        return (new ReflectionMethod(DB::class, $method))->invoke(null, ...$args);
    }

    public function testSanitizeBacktickQuotedAcceptsAHyphenatedIdentifier(): void
    {
        $this->assertSame('user-app_prod', $this->call('sanitizeBacktickQuoted', ['user-app_prod']));
    }

    public function testSanitizeBacktickQuotedRejectsABacktick(): void
    {
        $this->expectException(PDOException::class);
        $this->call('sanitizeBacktickQuoted', ['users` ; DROP TABLE users -- ']);
    }

    public function testSanitizeRejectsAnIdentifierWithSqlMetacharacters(): void
    {
        $this->expectException(PDOException::class);
        $this->call('sanitize', ['users; DROP TABLE users']);
    }

    public function testColumnsPassesAStarThrough(): void
    {
        $this->assertSame('*', $this->call('columns', ['*']));
    }

    public function testColumnsSanitizesASimpleColumnName(): void
    {
        $this->assertSame('email', $this->call('columns', ['email']));
    }

    public function testColumnsPassesExpressionsThroughUnchanged(): void
    {
        $this->assertSame('roles.name AS role', $this->call('columns', ['roles.name AS role']));
    }

    public function testColumnsHandlesAnArrayOfMixedColumnsAndExpressions(): void
    {
        $this->assertSame('id, roles.name AS role', $this->call('columns', [['id', 'roles.name AS role']]));
    }

    public function testBuildWhereProducesAnEqualityConditionByDefault(): void
    {
        [$clause, $params] = $this->call('buildWhere', [['email' => 'a@b.com']]);

        $this->assertSame('email = :email', $clause);
        $this->assertSame([':email' => 'a@b.com'], $params);
    }

    public function testBuildWhereSupportsAnExplicitOperator(): void
    {
        [$clause, $params] = $this->call('buildWhere', [['age' => ['>', 18]]]);

        $this->assertSame('age > :age', $clause);
        $this->assertSame([':age' => 18], $params);
    }

    public function testBuildWhereHandlesIsNullWithoutABoundParameter(): void
    {
        [$clause, $params] = $this->call('buildWhere', [['deleted_at' => ['IS', null]]]);

        $this->assertSame('deleted_at IS NULL', $clause);
        $this->assertSame([], $params);
    }

    public function testBuildWhereJoinsMultipleConditionsWithTheGivenSeparator(): void
    {
        [$clause] = $this->call('buildWhere', [['a' => 1, 'b' => 2], '', ' OR ']);

        $this->assertSame('a = :a OR b = :b', $clause);
    }

    public function testBuildWhereRejectsAnInvalidColumnName(): void
    {
        $this->expectException(PDOException::class);
        $this->call('buildWhere', [['1=1; --' => 'x']]);
    }

    public function testBuildWhereAcceptsATableQualifiedColumn(): void
    {
        [$clause, $params] = $this->call('buildWhere', [['users.email' => 'a@b.com']]);

        $this->assertSame('users.email = :users_email', $clause);
        $this->assertSame([':users_email' => 'a@b.com'], $params);
    }

    public function testBuildWhereDisambiguatesParamNamesThatCollideAfterNormalization(): void
    {
        // 'a.b' and 'a_b' both normalize to the same param name - the second must not
        // silently overwrite the first's bound value.
        [$clause, $params] = $this->call('buildWhere', [['a.b' => 1, 'a_b' => 2]]);

        $this->assertSame('a.b = :a_b AND a_b = :a_b_1', $clause);
        $this->assertSame([':a_b' => 1, ':a_b_1' => 2], $params);
    }

    public function testCombineWhereAndsThePlainWhereWithAParenthesizedOrGroup(): void
    {
        [$clause, $params] = $this->call('combineWhere', [['status' => 'active'], ['role' => 'admin', 'role2' => 'owner']]);

        $this->assertSame('status = :status AND (role = :or_role OR role2 = :or_role2)', $clause);
        $this->assertSame([':status' => 'active', ':or_role' => 'admin', ':or_role2' => 'owner'], $params);
    }

    public function testCombineWhereWithOnlyOrConditionsWrapsThemInParentheses(): void
    {
        [$clause] = $this->call('combineWhere', [[], ['role' => 'admin']]);

        $this->assertSame('(role = :or_role)', $clause);
    }

    public function testCombineWhereWithNeitherReturnsAnEmptyClause(): void
    {
        [$clause, $params] = $this->call('combineWhere', [[], []]);

        $this->assertSame('', $clause);
        $this->assertSame([], $params);
    }

    public function testCombineWhereDisambiguatesParamNamesThatCollideAcrossAndOrHalves(): void
    {
        // AND-side 'or_status' and OR-side 'status' (prefixed 'or_') both normalize to ':or_status'.
        [$clause, $params] = $this->call('combineWhere', [['or_status' => 'x'], ['status' => 'y']]);

        $this->assertSame('or_status = :or_status AND (status = :or_status_1)', $clause);
        $this->assertSame([':or_status' => 'x', ':or_status_1' => 'y'], $params);
    }

    public function testBuildJoinFromTheMainTable(): void
    {
        $clause = $this->call('buildJoin', ['users', ['id', ['user_roles', 'user_id']]]);

        $this->assertSame('LEFT JOIN user_roles ON users.id = user_roles.user_id', $clause);
    }

    public function testBuildJoinFromAnotherTable(): void
    {
        $clause = $this->call('buildJoin', ['users', [['user_roles', 'role_id'], ['roles', 'id']]]);

        $this->assertSame('LEFT JOIN roles ON user_roles.role_id = roles.id', $clause);
    }

    public function testBuildJoinWithMultipleJoins(): void
    {
        $clause = $this->call('buildJoin', ['users', [
            ['id', ['user_roles', 'user_id']],
            [['user_roles', 'role_id'], ['roles', 'id']],
        ]]);

        $this->assertSame('LEFT JOIN user_roles ON users.id = user_roles.user_id LEFT JOIN roles ON user_roles.role_id = roles.id', $clause);
    }

    public function testBuildJoinWithNoJoinsReturnsAnEmptyString(): void
    {
        $this->assertSame('', $this->call('buildJoin', ['users', []]));
    }

    public function testGroupByClauseWithNullReturnsEmpty(): void
    {
        $this->assertSame('', $this->call('groupByClause', [null]));
    }

    public function testGroupByClauseFromACommaSeparatedString(): void
    {
        $this->assertSame('GROUP BY status, role', $this->call('groupByClause', ['status, role']));
    }

    public function testGroupByClauseFromAnArray(): void
    {
        $this->assertSame('GROUP BY status, role', $this->call('groupByClause', [['status', 'role']]));
    }

    public function testGroupByClauseRejectsAnInvalidElement(): void
    {
        $this->expectException(PDOException::class);
        $this->call('groupByClause', ['status; DROP TABLE users']);
    }

    public function testOrderByClauseWithNullReturnsEmpty(): void
    {
        $this->assertSame('', $this->call('orderByClause', [null]));
    }

    public function testOrderByClauseDefaultsToNoDirectionSuffix(): void
    {
        $this->assertSame('ORDER BY created_at', $this->call('orderByClause', ['created_at']));
    }

    public function testOrderByClauseAcceptsAscAndDesc(): void
    {
        $this->assertSame('ORDER BY created_at DESC, name ASC', $this->call('orderByClause', ['created_at desc, name asc']));
    }

    public function testOrderByClauseAcceptsATableQualifiedColumn(): void
    {
        $this->assertSame('ORDER BY users.created_at DESC', $this->call('orderByClause', ['users.created_at DESC']));
    }

    public function testOrderByClauseRejectsAnInvalidDirection(): void
    {
        $this->expectException(PDOException::class);
        $this->call('orderByClause', ['created_at SIDEWAYS']);
    }

    public function testOrderByClauseRejectsAnInvalidElement(): void
    {
        $this->expectException(PDOException::class);
        $this->call('orderByClause', ['created_at; DROP TABLE users']);
    }
}
