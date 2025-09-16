<?php
/**
 * Portos International Compliance API
 * Gestión de certificaciones y compliance freight forwarding
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
use OrangeHRM\Entity\Employee;

class PortosComplianceAPI extends Endpoint implements CrudEndpoint
{
    public const PARAMETER_DAYS_WARNING = 'daysWarning';
    public const PARAMETER_CERTIFICATION_TYPE = 'certificationType';
    public const PARAMETER_DEPARTMENT = 'department';
    public const PARAMETER_STATUS = 'status';

    /**
     * API Compliance: Certificaciones por vencer
     * GET /api/v2/portos/compliance/certificaciones
     */
    public function getExpiringCertifications(): EndpointCollectionResult
    {
        $daysWarning = $this->getRequestParams()->getInt(
            RequestParams::PARAM_TYPE_QUERY,
            self::PARAMETER_DAYS_WARNING,
            30
        );

        $certificationType = $this->getRequestParams()->getString(
            RequestParams::PARAM_TYPE_QUERY,
            self::PARAMETER_CERTIFICATION_TYPE
        );

        $department = $this->getRequestParams()->getString(
            RequestParams::PARAM_TYPE_QUERY,
            self::PARAMETER_DEPARTMENT
        );

        $status = $this->getRequestParams()->getString(
            RequestParams::PARAM_TYPE_QUERY,
            self::PARAMETER_STATUS,
            'warning'
        );

        // Query base para empleados activos
        $qb = $this->createQueryBuilder(Employee::class, 'e')
            ->leftJoin('e.jobTitle', 'jt')
            ->leftJoin('e.subunit', 's')
            ->where('e.empStatus = :status')
            ->setParameter('status', Employee::STATUS_ACTIVE);

        if ($department) {
            $qb->andWhere('s.name = :department')
               ->setParameter('department', $department);
        }

        $employees = $qb->getQuery()->getResult();

        // Obtener certificaciones desde custom fields
        $certifications = [];
        
        foreach ($employees as $employee) {
            $empCertifications = $this->getEmployeeCertifications($employee, $daysWarning, $certificationType, $status);
            $certifications = array_merge($certifications, $empCertifications);
        }

        // Ordenar por días hasta vencimiento
        usort($certifications, function($a, $b) {
            return $a['daysUntilExpiry'] <=> $b['daysUntilExpiry'];
        });

        // Estadísticas
        $totalCertifications = count($certifications);
        $expiredCount = count(array_filter($certifications, fn($c) => $c['status'] === 'expired'));
        $criticalCount = count(array_filter($certifications, fn($c) => $c['status'] === 'critical'));
        $warningCount = count(array_filter($certifications, fn($c) => $c['status'] === 'warning'));

        return new EndpointCollectionResult(
            ArrayModel::class,
            $certifications,
            new ParameterBag([
                'total' => $totalCertifications,
                'expired' => $expiredCount,
                'critical' => $criticalCount, // < 7 días
                'warning' => $warningCount,   // 7-30 días
                'daysWarning' => $daysWarning,
                'certificationType' => $certificationType,
                'department' => $department,
                'generatedAt' => (new \DateTime())->format('Y-m-d H:i:s')
            ])
        );
    }

    /**
     * Obtener certificaciones de un empleado específico
     */
    private function getEmployeeCertifications(Employee $employee, int $daysWarning, ?string $typeFilter, string $statusFilter): array
    {
        $certifications = [];
        $today = new \DateTime();

        // Mapeo de certificaciones (field_num => info)
        $certificationTypes = [
            'iata' => [
                'field_num' => 2,
                'active_field' => 1,
                'active_value' => 'Yes',
                'name' => 'IATA',
                'description' => 'IATA Cargo Certification'
            ],
            'customs' => [
                'field_num' => 6,
                'active_field' => 4,
                'active_value' => 'Active',
                'name' => 'Customs License',
                'description' => 'Licencia Agente Aduanal'
            ],
            'dangerous_goods' => [
                'field_num' => 8,
                'active_field' => 7,
                'active_value' => ['IATA DG Cat 1', 'IATA DG Cat 3', 'IATA DG Cat 6', 'IMO IMDG'],
                'name' => 'Dangerous Goods',
                'description' => 'Dangerous Goods Certification'
            ],
            'ctpat' => [
                'field_num' => 10,
                'active_field' => 9,
                'active_value' => ['Certified', 'Validated'],
                'name' => 'CTPAT',
                'description' => 'CTPAT Security Certification'
            ]
        ];

        foreach ($certificationTypes as $typeKey => $certType) {
            // Filtrar por tipo si se especifica
            if ($typeFilter && $typeKey !== $typeFilter) {
                continue;
            }

            // Obtener valores de custom fields usando conexión directa
            $expiryValue = $this->getCustomFieldValue($employee->getEmpNumber(), $certType['field_num']);
            $activeValue = $this->getCustomFieldValue($employee->getEmpNumber(), $certType['active_field']);

            // Verificar si tiene la certificación activa
            $isActive = false;
            if (is_array($certType['active_value'])) {
                $isActive = in_array($activeValue, $certType['active_value']);
            } else {
                $isActive = $activeValue === $certType['active_value'];
            }

            if (!$isActive || !$expiryValue) {
                continue;
            }

            try {
                $expiryDate = new \DateTime($expiryValue);
                $daysUntilExpiry = $today->diff($expiryDate)->days;
                $isExpired = $expiryDate < $today;

                if ($isExpired) {
                    $daysUntilExpiry = -$daysUntilExpiry;
                }

                // Determinar status
                $status = 'valid';
                if ($isExpired) {
                    $status = 'expired';
                } elseif ($daysUntilExpiry <= 7) {
                    $status = 'critical';
                } elseif ($daysUntilExpiry <= $daysWarning) {
                    $status = 'warning';
                }

                // Filtrar por status si se especifica
                if ($statusFilter !== 'all' && $status !== $statusFilter && 
                    !($statusFilter === 'warning' && in_array($status, ['warning', 'critical', 'expired']))) {
                    continue;
                }

                $certifications[] = [
                    'empNumber' => $employee->getEmpNumber(),
                    'employeeId' => $employee->getEmployeeId(),
                    'employeeName' => trim($employee->getFirstName() . ' ' . $employee->getLastName()),
                    'department' => $employee->getSubunit() ? $employee->getSubunit()->getName() : 'N/A',
                    'jobTitle' => $employee->getJobTitle() ? $employee->getJobTitle()->getJobTitleName() : 'N/A',
                    'certificationType' => $typeKey,
                    'certificationName' => $certType['name'],
                    'certificationDescription' => $certType['description'],
                    'expiryDate' => $expiryDate->format('Y-m-d'),
                    'expiryDateFormatted' => $expiryDate->format('d/m/Y'),
                    'daysUntilExpiry' => $isExpired ? $daysUntilExpiry : $daysUntilExpiry,
                    'status' => $status,
                    'statusLabel' => $this->getStatusLabel($status),
                    'priority' => $this->getStatusPriority($status),
                    'isExpired' => $isExpired,
                    'activeValue' => $activeValue
                ];

            } catch (\Exception $e) {
                // Fecha inválida, ignorar
                continue;
            }
        }

        return $certifications;
    }

    /**
     * Obtener valor de custom field
     */
    private function getCustomFieldValue(int $empNumber, int $fieldNum): ?string
    {
        $connection = $this->getEntityManager()->getConnection();
        
        $result = $connection->executeQuery(
            'SELECT value FROM ohrm_employee_custom_field_value WHERE emp_number = ? AND field_num = ?',
            [$empNumber, $fieldNum]
        )->fetchOne();

        return $result ?: null;
    }

    private function getStatusLabel(string $status): string
    {
        $labels = [
            'valid' => 'Válida',
            'warning' => 'Por Vencer',
            'critical' => 'Crítica',
            'expired' => 'Vencida'
        ];
        return $labels[$status] ?? 'Desconocido';
    }

    private function getStatusPriority(string $status): int
    {
        $priorities = [
            'expired' => 1,
            'critical' => 2,
            'warning' => 3,
            'valid' => 4
        ];
        return $priorities[$status] ?? 5;
    }

    /**
     * API Compliance: Resumen de compliance por departamento
     * GET /api/v2/portos/compliance/departmental-summary
     */
    public function getDepartmentalSummary(): EndpointResourceResult
    {
        $connection = $this->getEntityManager()->getConnection();
        
        $query = "
            SELECT 
                s.name as department,
                COUNT(DISTINCT e.emp_number) as total_employees,
                COUNT(DISTINCT CASE WHEN cf_iata_active.value = 'Yes' THEN e.emp_number END) as iata_certified,
                COUNT(DISTINCT CASE WHEN cf_customs_active.value = 'Active' THEN e.emp_number END) as customs_licensed,
                COUNT(DISTINCT CASE WHEN cf_dg_active.value NOT IN ('None', '') AND cf_dg_active.value IS NOT NULL THEN e.emp_number END) as dg_certified,
                COUNT(DISTINCT CASE WHEN cf_ctpat_active.value IN ('Certified', 'Validated') THEN e.emp_number END) as ctpat_certified
            FROM ohrm_subunit s
            LEFT JOIN ohrm_employee e ON e.work_station = s.id AND e.emp_status = 1
            LEFT JOIN ohrm_employee_custom_field_value cf_iata_active ON e.emp_number = cf_iata_active.emp_number AND cf_iata_active.field_num = 1
            LEFT JOIN ohrm_employee_custom_field_value cf_customs_active ON e.emp_number = cf_customs_active.emp_number AND cf_customs_active.field_num = 4
            LEFT JOIN ohrm_employee_custom_field_value cf_dg_active ON e.emp_number = cf_dg_active.emp_number AND cf_dg_active.field_num = 7
            LEFT JOIN ohrm_employee_custom_field_value cf_ctpat_active ON e.emp_number = cf_ctpat_active.emp_number AND cf_ctpat_active.field_num = 9
            WHERE s.level > 0
            GROUP BY s.id, s.name
            ORDER BY s.name
        ";

        $departments = $connection->executeQuery($query)->fetchAllAssociative();

        $summary = [
            'departments' => array_map(function($dept) {
                $total = (int)$dept['total_employees'];
                return [
                    'department' => $dept['department'],
                    'totalEmployees' => $total,
                    'certifications' => [
                        'iata' => [
                            'certified' => (int)$dept['iata_certified'],
                            'percentage' => $total > 0 ? round(((int)$dept['iata_certified'] / $total) * 100, 1) : 0
                        ],
                        'customs' => [
                            'certified' => (int)$dept['customs_licensed'],
                            'percentage' => $total > 0 ? round(((int)$dept['customs_licensed'] / $total) * 100, 1) : 0
                        ],
                        'dangerousGoods' => [
                            'certified' => (int)$dept['dg_certified'],
                            'percentage' => $total > 0 ? round(((int)$dept['dg_certified'] / $total) * 100, 1) : 0
                        ],
                        'ctpat' => [
                            'certified' => (int)$dept['ctpat_certified'],
                            'percentage' => $total > 0 ? round(((int)$dept['ctpat_certified'] / $total) * 100, 1) : 0
                        ]
                    ]
                ];
            }, $departments),
            'totals' => [
                'totalEmployees' => array_sum(array_column($departments, 'total_employees')),
                'iataCertified' => array_sum(array_column($departments, 'iata_certified')),
                'customsLicensed' => array_sum(array_column($departments, 'customs_licensed')),
                'dgCertified' => array_sum(array_column($departments, 'dg_certified')),
                'ctpatCertified' => array_sum(array_column($departments, 'ctpat_certified'))
            ],
            'metadata' => [
                'generatedAt' => (new \DateTime())->format('Y-m-d H:i:s'),
                'organization' => 'Portos International'
            ]
        ];

        return new EndpointResourceResult(ArrayModel::class, $summary);
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule(
                self::PARAMETER_DAYS_WARNING,
                new Rule(Rules::POSITIVE),
                new Rule(Rules::LESS_THAN, [91])
            ),
            new ParamRule(
                self::PARAMETER_CERTIFICATION_TYPE,
                new Rule(Rules::IN, [['iata', 'customs', 'dangerous_goods', 'ctpat']])
            ),
            new ParamRule(
                self::PARAMETER_DEPARTMENT,
                new Rule(Rules::STRING_TYPE)
            ),
            new ParamRule(
                self::PARAMETER_STATUS,
                new Rule(Rules::IN, [['all', 'valid', 'warning', 'critical', 'expired']])
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function getAll(): EndpointCollectionResult
    {
        return $this->getExpiringCertifications();
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
    public function getOne(): EndpointResourceResult
    {
        return $this->getDepartmentalSummary();
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
}