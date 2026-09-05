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
final class DBTest extends TestCase
{
    public function testColumnsPassesAStarThrough(): void
    {
        // Act + Assert
        $this->assertSame('*', $this->call('columns', ['*']));
    }

    private function call(string $method, array $args): mixed
    {
        return (new ReflectionMethod(DB::class, $method))->invoke(null, ...$args);
    }

    public function testColumnsSanitizesASimpleColumnName(): void
    {
        // Act + Assert
        $this->assertSame('email', $this->call('columns', ['email']));
    }

    public function testColumnsPassesExpressionsThroughUnchanged(): void
    {
        // Act + Assert
        $this->assertSame('roles.name AS role', $this->call('columns', ['roles.name AS role']));
    }

    public function testColumnsHandlesAnArrayOfMixedColumnsAndExpressions(): void
    {
        // Act + Assert
        $this->assertSame('id, roles.name AS role', $this->call('columns', [['id', 'roles.name AS role']]));
    }

    public function testSanitizeAcceptsAPlainIdentifier(): void
    {
        // Act + Assert
        $this->assertSame('users', $this->call('sanitize', ['users']));
    }

    public function testSanitizeRejectsAnIdentifierWithSqlMetacharacters(): void
    {
        // Arrange
        $this->expectException(PDOException::class);

        // Act
        $this->call('sanitize', ['users; DROP TABLE users']);
    }

    public function testBuildJoinFromTheMainTable(): void
    {
        // Act
        $clause = $this->call('buildJoin', ['users', ['id', ['user_roles', 'user_id']]]);

        // Assert
        $this->assertSame('LEFT JOIN user_roles ON users.id = user_roles.user_id', $clause);
    }

    public function testBuildJoinFromAnotherTable(): void
    {
        // Act
        $clause = $this->call('buildJoin', ['users', [['user_roles', 'role_id'], ['roles', 'id']]]);

        // Assert
        $this->assertSame('LEFT JOIN roles ON user_roles.role_id = roles.id', $clause);
    }

    public function testBuildJoinWithMultipleJoins(): void
    {
        // Act
        $clause = $this->call('buildJoin', ['users', [
            ['id', ['user_roles', 'user_id']],
            [['user_roles', 'role_id'], ['roles', 'id']],
        ]]);

        // Assert
        $this->assertSame('LEFT JOIN user_roles ON users.id = user_roles.user_id LEFT JOIN roles ON user_roles.role_id = roles.id', $clause);
    }

    public function testBuildJoinWithNoJoinsReturnsAnEmptyString(): void
    {
        // Act + Assert
        $this->assertSame('', $this->call('buildJoin', ['users', []]));
    }

    public function testCombineWhereAndsThePlainWhereWithAParenthesizedOrGroup(): void
    {
        // Act
        [$clause, $params] = $this->call('combineWhere', [['status' => 'active'], ['role' => 'admin', 'role2' => 'owner']]);

        // Assert
        $this->assertSame('status = :status AND (role = :or_role OR role2 = :or_role2)', $clause);
        $this->assertSame([':status' => 'active', ':or_role' => 'admin', ':or_role2' => 'owner'], $params);
    }

    public function testCombineWhereWithOnlyOrConditionsWrapsThemInParentheses(): void
    {
        // Act
        [$clause] = $this->call('combineWhere', [[], ['role' => 'admin']]);

        // Assert
        $this->assertSame('(role = :or_role)', $clause);
    }

    public function testCombineWhereWithNeitherReturnsAnEmptyClause(): void
    {
        // Act
        [$clause, $params] = $this->call('combineWhere', [[], []]);

        // Assert
        $this->assertSame('', $clause);
        $this->assertSame([], $params);
    }

    public function testCombineWhereDisambiguatesParamNamesThatCollideAcrossAndOrHalves(): void
    {
        // Act
        // AND-side 'or_status' and OR-side 'status' (prefixed 'or_') both normalize to ':or_status'.
        [$clause, $params] = $this->call('combineWhere', [['or_status' => 'x'], ['status' => 'y']]);

        // Assert
        $this->assertSame('or_status = :or_status AND (status = :or_status_1)', $clause);
        $this->assertSame([':or_status' => 'x', ':or_status_1' => 'y'], $params);
    }

    public function testBuildWhereProducesAnEqualityConditionByDefault(): void
    {
        // Act
        [$clause, $params] = $this->call('buildWhere', [['email' => 'a@b.com']]);

        // Assert
        $this->assertSame('email = :email', $clause);
        $this->assertSame([':email' => 'a@b.com'], $params);
    }

    public function testBuildWhereSupportsAnExplicitOperator(): void
    {
        // Act
        [$clause, $params] = $this->call('buildWhere', [['age' => ['>', 18]]]);

        // Assert
        $this->assertSame('age > :age', $clause);
        $this->assertSame([':age' => 18], $params);
    }

    public function testBuildWhereHandlesIsNullWithoutABoundParameter(): void
    {
        // Act
        [$clause, $params] = $this->call('buildWhere', [['deleted_at' => ['IS', null]]]);

        // Assert
        $this->assertSame('deleted_at IS NULL', $clause);
        $this->assertSame([], $params);
    }

    public function testBuildWhereSupportsIn(): void
    {
        // Act
        [$clause, $params] = $this->call('buildWhere', [['role_id' => ['IN', [1, 2, 3]]]]);

        // Assert
        $this->assertSame('role_id IN (:role_id_0, :role_id_1, :role_id_2)', $clause);
        $this->assertSame([':role_id_0' => 1, ':role_id_1' => 2, ':role_id_2' => 3], $params);
    }

    public function testBuildWhereSupportsNotIn(): void
    {
        // Act
        [$clause, $params] = $this->call('buildWhere', [['role_id' => ['NOT IN', [1, 2]]]]);

        // Assert
        $this->assertSame('role_id NOT IN (:role_id_0, :role_id_1)', $clause);
        $this->assertSame([':role_id_0' => 1, ':role_id_1' => 2], $params);
    }

    public function testBuildWhereEmptyInListMatchesNothingWithoutBoundParameters(): void
    {
        // Act
        [$clause, $params] = $this->call('buildWhere', [['role_id' => ['IN', []]]]);

        // Assert
        $this->assertSame('1 = 0', $clause);
        $this->assertSame([], $params);
    }

    public function testBuildWhereEmptyNotInListMatchesEverythingWithoutBoundParameters(): void
    {
        // Act
        [$clause, $params] = $this->call('buildWhere', [['role_id' => ['NOT IN', []]]]);

        // Assert
        $this->assertSame('1 = 1', $clause);
        $this->assertSame([], $params);
    }

    public function testBuildWhereJoinsMultipleConditionsWithTheGivenSeparator(): void
    {
        // Act
        [$clause] = $this->call('buildWhere', [['a' => 1, 'b' => 2], '', ' OR ']);

        // Assert
        $this->assertSame('a = :a OR b = :b', $clause);
    }

    public function testBuildWhereRejectsAnInvalidColumnName(): void
    {
        // Arrange
        $this->expectException(PDOException::class);

        // Act
        $this->call('buildWhere', [['1=1; --' => 'x']]);
    }

    public function testBuildWhereAcceptsATableQualifiedColumn(): void
    {
        // Act
        [$clause, $params] = $this->call('buildWhere', [['users.email' => 'a@b.com']]);

        // Assert
        $this->assertSame('users.email = :users_email', $clause);
        $this->assertSame([':users_email' => 'a@b.com'], $params);
    }

    public function testBuildWhereDisambiguatesParamNamesThatCollideAfterNormalization(): void
    {
        // Act
        // 'a.b' and 'a_b' both normalize to the same param name; the second must not silently overwrite the first's bound value.
        [$clause, $params] = $this->call('buildWhere', [['a.b' => 1, 'a_b' => 2]]);

        // Assert
        $this->assertSame('a.b = :a_b AND a_b = :a_b_1', $clause);
        $this->assertSame([':a_b' => 1, ':a_b_1' => 2], $params);
    }

    public function testGroupByClauseWithNullReturnsEmpty(): void
    {
        // Act + Assert
        $this->assertSame('', $this->call('groupByClause', [null]));
    }

    public function testGroupByClauseFromACommaSeparatedString(): void
    {
        // Act + Assert
        $this->assertSame('GROUP BY status, role', $this->call('groupByClause', ['status, role']));
    }

    public function testGroupByClauseFromAnArray(): void
    {
        // Act + Assert
        $this->assertSame('GROUP BY status, role', $this->call('groupByClause', [['status', 'role']]));
    }

    public function testGroupByClauseRejectsAnInvalidElement(): void
    {
        // Arrange
        $this->expectException(PDOException::class);

        // Act
        $this->call('groupByClause', ['status; DROP TABLE users']);
    }

    public function testOrderByClauseWithNullReturnsEmpty(): void
    {
        // Act + Assert
        $this->assertSame('', $this->call('orderByClause', [null]));
    }

    public function testOrderByClauseDefaultsToNoDirectionSuffix(): void
    {
        // Act + Assert
        $this->assertSame('ORDER BY created_at', $this->call('orderByClause', ['created_at']));
    }

    public function testOrderByClauseAcceptsAscAndDesc(): void
    {
        // Act + Assert
        $this->assertSame('ORDER BY created_at DESC, name ASC', $this->call('orderByClause', ['created_at desc, name asc']));
    }

    public function testOrderByClauseAcceptsATableQualifiedColumn(): void
    {
        // Act + Assert
        $this->assertSame('ORDER BY users.created_at DESC', $this->call('orderByClause', ['users.created_at DESC']));
    }

    public function testOrderByClauseRejectsAnInvalidDirection(): void
    {
        // Arrange
        $this->expectException(PDOException::class);

        // Act
        $this->call('orderByClause', ['created_at SIDEWAYS']);
    }

    public function testOrderByClauseRejectsAnInvalidElement(): void
    {
        // Arrange
        $this->expectException(PDOException::class);

        // Act
        $this->call('orderByClause', ['created_at; DROP TABLE users']);
    }

    public function testSanitizeBacktickQuotedAcceptsAHyphenatedIdentifier(): void
    {
        // Act + Assert
        $this->assertSame('user-app_prod', $this->call('sanitizeBacktickQuoted', ['user-app_prod']));
    }

    public function testSanitizeBacktickQuotedRejectsABacktick(): void
    {
        // Arrange
        $this->expectException(PDOException::class);

        // Act
        $this->call('sanitizeBacktickQuoted', ['users` ; DROP TABLE users -- ']);
    }
}
