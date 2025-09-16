<?php
/**
 * Portos International Dashboard API
 * APIs personalizadas para dashboard corporativo
 * 
 * @author Claude Code - OrangeHRM Customization
 * @version 2.0
 * @since 2024
 */

namespace OrangeHRM\Portos\Api\v2;

use OrangeHRM\Core\Api\CommonParams;
use OrangeHRM\Core\Api\V2\CrudEndpoint;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointCollectionResult;
use OrangeHRM\Core\Api\V2\EndpointResourceResult;
use OrangeHRM\Core\Api\V2\Model\ArrayModel;
use OrangeHRM\Core\Api\V2\ParameterBag;
use OrangeHRM\Core\Api\V2\RequestParams;
use OrangeHRM\Core\Api\V2\Validator\ParamRule;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Api\V2\Validator\Rule;
use OrangeHRM\Core\Api\V2\Validator\Rules;
use OrangeHRM\Core\Service\DateTimeHelperService;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Pim\Service\EmployeeService;

class PortosDashboardAPI extends Endpoint implements CrudEndpoint
{
    public const PARAMETER_DEPARTMENT = 'department';
    public const PARAMETER_DAYS = 'days';
    public const PARAMETER_ACTIVE_ONLY = 'activeOnly';

    /**
     * @var EmployeeService|null
     */
    protected ?EmployeeService $employeeService = null;

    /**
     * @return EmployeeService
     */
    public function getEmployeeService(): EmployeeService
    {
        if (is_null($this->employeeService)) {
            $this->employeeService = new EmployeeService();
        }
        return $this->employeeService;
    }

    /**
     * API Dashboard: Cumpleaños próximos
     * GET /api/v2/portos/dashboard/cumpleanos
     */
    public function getUpcomingBirthdays(): EndpointCollectionResult
    {
        $days = $this->getRequestParams()->getInt(
            RequestParams::PARAM_TYPE_QUERY,
            self::PARAMETER_DAYS,
            7
        );

        $activeOnly = $this->getRequestParams()->getBoolean(
            RequestParams::PARAM_TYPE_QUERY,
            self::PARAMETER_ACTIVE_ONLY,
            true
        );

        $qb = $this->createQueryBuilder(Employee::class, 'e')
            ->leftJoin('e.jobTitle', 'jt')
            ->leftJoin('e.subunit', 's')
            ->leftJoin('e.empPicture', 'pic')
            ->select([
                'e.empNumber',
                'e.firstName',
                'e.lastName',
                'e.employeeId',
                'e.empBirthday',
                'jt.jobTitleName',
                's.name as department',
                'pic.filename as photo'
            ]);

        if ($activeOnly) {
            $qb->andWhere('e.empStatus = :status')
               ->setParameter('status', Employee::STATUS_ACTIVE);
        }

        // Filtrar por cumpleaños en los próximos X días
        $today = new \DateTime();
        $endDate = (clone $today)->add(new \DateInterval("P{$days}D"));
        
        // Lógica para manejar cumpleaños que cruzan fin de año
        $currentYear = $today->format('Y');
        $startDay = $today->format('m-d');
        $endDay = $endDate->format('m-d');
        
        if ($startDay <= $endDay) {
            // Mismo año
            $qb->andWhere("DATE_FORMAT(e.empBirthday, '%m-%d') BETWEEN :startDay AND :endDay")
               ->setParameter('startDay', $startDay)
               ->setParameter('endDay', $endDay);
        } else {
            // Cruza fin de año
            $qb->andWhere("DATE_FORMAT(e.empBirthday, '%m-%d') >= :startDay OR DATE_FORMAT(e.empBirthday, '%m-%d') <= :endDay")
               ->setParameter('startDay', $startDay)
               ->setParameter('endDay', $endDay);
        }

        $qb->orderBy("DAYOFYEAR(STR_TO_DATE(CONCAT('{$currentYear}-', DATE_FORMAT(e.empBirthday, '%m-%d')), '%Y-%m-%d'))", 'ASC');

        $employees = $qb->getQuery()->getArrayResult();

        // Calcular días hasta el cumpleaños y edad
        $birthdays = [];
        foreach ($employees as $emp) {
            if ($emp['empBirthday']) {
                $birthday = clone $emp['empBirthday'];
                $birthday->setDate($currentYear, $birthday->format('m'), $birthday->format('d'));
                
                // Si ya pasó este año, usar el próximo año
                if ($birthday < $today) {
                    $birthday->setDate($currentYear + 1, $birthday->format('m'), $birthday->format('d'));
                }
                
                $daysUntil = $today->diff($birthday)->days;
                $age = $today->diff($emp['empBirthday'])->y + 1; // Edad que cumplirá

                $birthdays[] = [
                    'empNumber' => $emp['empNumber'],
                    'employeeId' => $emp['employeeId'],
                    'fullName' => trim($emp['firstName'] . ' ' . $emp['lastName']),
                    'firstName' => $emp['firstName'],
                    'lastName' => $emp['lastName'],
                    'jobTitle' => $emp['jobTitleName'],
                    'department' => $emp['department'],
                    'birthday' => $emp['empBirthday']->format('Y-m-d'),
                    'birthdayFormatted' => $emp['empBirthday']->format('d/m'),
                    'daysUntilBirthday' => $daysUntil,
                    'ageWillBe' => $age,
                    'photo' => $emp['photo'] ? "/pim/viewPhoto/empNumber/{$emp['empNumber']}" : null,
                    'isToday' => $daysUntil === 0
                ];
            }
        }

        // Ordenar por días hasta cumpleaños
        usort($birthdays, function($a, $b) {
            return $a['daysUntilBirthday'] <=> $b['daysUntilBirthday'];
        });

        return new EndpointCollectionResult(
            ArrayModel::class,
            $birthdays,
            new ParameterBag([
                'total' => count($birthdays),
                'days' => $days,
                'todayCount' => array_filter($birthdays, fn($b) => $b['isToday']),
                'generatedAt' => (new \DateTime())->format('Y-m-d H:i:s')
            ])
        );
    }

    /**
     * API Dashboard: KPIs Básicos
     * GET /api/v2/portos/dashboard/kpis-basicos
     */
    public function getBasicKPIs(): EndpointResourceResult
    {
        $today = new \DateTime();
        
        // Empleados activos
        $activeEmployees = $this->createQueryBuilder(Employee::class, 'e')
            ->select('COUNT(e.empNumber)')
            ->where('e.empStatus = :status')
            ->setParameter('status', Employee::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        // Empleados disponibles hoy (no en leave)
        $availableToday = $this->createQueryBuilder(Employee::class, 'e')
            ->select('COUNT(DISTINCT e.empNumber)')
            ->leftJoin('e.employeeLeaves', 'el')
            ->leftJoin('el.leave', 'l')
            ->where('e.empStatus = :status')
            ->andWhere('(l.id IS NULL OR l.date != :today OR l.status != :approved)')
            ->setParameter('status', Employee::STATUS_ACTIVE)
            ->setParameter('today', $today->format('Y-m-d'))
            ->setParameter('approved', 'SCHEDULED')
            ->getQuery()
            ->getSingleScalarResult();

        // Porcentaje de asistencia del día
        $attendancePercentage = $activeEmployees > 0 ? 
            round(($availableToday / $activeEmployees) * 100, 1) : 0;

        // Vacaciones pendientes de aprobación (estimado)
        $pendingLeaves = $this->createQueryBuilder('OrangeHRM\Entity\Leave', 'l')
            ->select('COUNT(l.id)')
            ->where('l.status = :pending')
            ->andWhere('l.date >= :today')
            ->setParameter('pending', 'PENDING_APPROVAL')
            ->setParameter('today', $today->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();

        // Certificaciones por vencer (próximos 30 días) - usando custom fields
        $expiringCerts = $this->getEntityManager()
            ->getConnection()
            ->executeQuery("
                SELECT COUNT(*) as total
                FROM (
                    SELECT e.emp_number
                    FROM ohrm_employee e
                    LEFT JOIN ohrm_employee_custom_field_value cf_iata ON e.emp_number = cf_iata.emp_number AND cf_iata.field_num = 2
                    LEFT JOIN ohrm_employee_custom_field_value cf_customs ON e.emp_number = cf_customs.emp_number AND cf_customs.field_num = 6
                    LEFT JOIN ohrm_employee_custom_field_value cf_dg ON e.emp_number = cf_dg.emp_number AND cf_dg.field_num = 8
                    LEFT JOIN ohrm_employee_custom_field_value cf_ctpat ON e.emp_number = cf_ctpat.emp_number AND cf_ctpat.field_num = 10
                    WHERE e.emp_status = 1
                    AND (
                        (cf_iata.value IS NOT NULL AND DATEDIFF(STR_TO_DATE(cf_iata.value, '%Y-%m-%d'), CURDATE()) BETWEEN 0 AND 30) OR
                        (cf_customs.value IS NOT NULL AND DATEDIFF(STR_TO_DATE(cf_customs.value, '%Y-%m-%d'), CURDATE()) BETWEEN 0 AND 30) OR
                        (cf_dg.value IS NOT NULL AND DATEDIFF(STR_TO_DATE(cf_dg.value, '%Y-%m-%d'), CURDATE()) BETWEEN 0 AND 30) OR
                        (cf_ctpat.value IS NOT NULL AND DATEDIFF(STR_TO_DATE(cf_ctpat.value, '%Y-%m-%d'), CURDATE()) BETWEEN 0 AND 30)
                    )
                    GROUP BY e.emp_number
                ) as expiring
            ")
            ->fetchOne();

        // Disponibilidad por departamento
        $departmentAvailability = $this->getEntityManager()
            ->getConnection()
            ->executeQuery("
                SELECT 
                    s.name as department,
                    COUNT(e.emp_number) as total_employees,
                    SUM(CASE WHEN available.emp_number IS NOT NULL THEN 1 ELSE 0 END) as available_count
                FROM ohrm_subunit s
                LEFT JOIN ohrm_employee e ON e.work_station = s.id AND e.emp_status = 1
                LEFT JOIN (
                    SELECT DISTINCT e2.emp_number
                    FROM ohrm_employee e2
                    LEFT JOIN ohrm_employee_leave el ON e2.emp_number = el.emp_number
                    LEFT JOIN ohrm_leave l ON el.leave_id = l.id AND l.date = :today AND l.status = 'SCHEDULED'
                    WHERE e2.emp_status = 1 AND l.id IS NULL
                ) available ON e.emp_number = available.emp_number
                WHERE s.level > 0
                GROUP BY s.id, s.name
                ORDER BY s.name
            ", ['today' => $today->format('Y-m-d')])
            ->fetchAllAssociative();

        $kpis = [
            'summary' => [
                'activeEmployees' => (int)$activeEmployees,
                'availableToday' => (int)$availableToday,
                'attendancePercentage' => $attendancePercentage,
                'pendingLeaveApprovals' => (int)$pendingLeaves,
                'expiringCertifications' => (int)$expiringCerts
            ],
            'departmentAvailability' => array_map(function($dept) {
                $total = (int)$dept['total_employees'];
                $available = (int)$dept['available_count'];
                return [
                    'department' => $dept['department'],
                    'totalEmployees' => $total,
                    'availableToday' => $available,
                    'availabilityPercentage' => $total > 0 ? round(($available / $total) * 100, 1) : 0
                ];
            }, $departmentAvailability),
            'metadata' => [
                'date' => $today->format('Y-m-d'),
                'time' => $today->format('H:i:s'),
                'timezone' => 'America/Mexico_City',
                'generatedAt' => $today->format('Y-m-d H:i:s')
            ]
        ];

        return new EndpointResourceResult(ArrayModel::class, $kpis);
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForGetOne(): ParamRuleCollection
    {
        return new ParamRuleCollection();
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule(
                self::PARAMETER_DAYS,
                new Rule(Rules::POSITIVE),
                new Rule(Rules::LESS_THAN, [31])
            ),
            new ParamRule(
                self::PARAMETER_ACTIVE_ONLY,
                new Rule(Rules::BOOL_VAL)
            ),
            new ParamRule(
                self::PARAMETER_DEPARTMENT,
                new Rule(Rules::STRING_TYPE)
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function create(): EndpointResourceResult
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForCreate(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function delete(): EndpointResourceResult
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForDelete(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function update(): EndpointResourceResult
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForUpdate(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function getOne(): EndpointResourceResult
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function getAll(): EndpointCollectionResult
    {
        // Default endpoint returns basic info
        return new EndpointCollectionResult(
            ArrayModel::class,
            [
                [
                    'name' => 'Portos International Dashboard API',
                    'version' => '2.0',
                    'endpoints' => [
                        'GET /api/v2/portos/dashboard/cumpleanos',
                        'GET /api/v2/portos/dashboard/kpis-basicos',
                        'GET /api/v2/portos/dashboard/anuncios',
                        'GET /api/v2/portos/dashboard/disponibilidad',
                        'GET /api/v2/portos/compliance/certificaciones'
                    ],
                    'organization' => 'Portos International',
                    'location' => 'Monterrey, N.L., México'
                ]
            ]
        );
    }
}