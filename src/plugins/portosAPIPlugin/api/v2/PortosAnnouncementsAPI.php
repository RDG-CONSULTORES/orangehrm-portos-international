<?php
/**
 * Portos International Announcements API
 * Gestión de anuncios para dashboard corporativo
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

class PortosAnnouncementsAPI extends Endpoint implements CrudEndpoint
{
    public const PARAMETER_DAYS = 'days';
    public const PARAMETER_PRIORITY = 'priority';
    public const PARAMETER_ACTIVE_ONLY = 'activeOnly';

    /**
     * API Dashboard: Anuncios activos
     * GET /api/v2/portos/dashboard/anuncios
     */
    public function getActiveAnnouncements(): EndpointCollectionResult
    {
        $days = $this->getRequestParams()->getInt(
            RequestParams::PARAM_TYPE_QUERY,
            self::PARAMETER_DAYS,
            30
        );

        $priority = $this->getRequestParams()->getString(
            RequestParams::PARAM_TYPE_QUERY,
            self::PARAMETER_PRIORITY
        );

        $activeOnly = $this->getRequestParams()->getBoolean(
            RequestParams::PARAM_TYPE_QUERY,
            self::PARAMETER_ACTIVE_ONLY,
            true
        );

        // Por ahora simular datos de anuncios hasta que se implemente tabla específica
        $announcements = $this->getSimulatedAnnouncements($days, $priority, $activeOnly);

        return new EndpointCollectionResult(
            ArrayModel::class,
            $announcements,
            new ParameterBag([
                'total' => count($announcements),
                'days' => $days,
                'priority' => $priority,
                'generatedAt' => (new \DateTime())->format('Y-m-d H:i:s')
            ])
        );
    }

    /**
     * Datos simulados de anuncios para demo
     */
    private function getSimulatedAnnouncements(int $days, ?string $priority, bool $activeOnly): array
    {
        $baseAnnouncements = [
            [
                'id' => 1,
                'title' => 'Nueva certificación CTPAT renovada',
                'content' => 'Portos International ha renovado exitosamente su certificación CTPAT por 3 años más. Esto refuerza nuestro compromiso con la seguridad en el comercio internacional.',
                'priority' => 'high',
                'category' => 'compliance',
                'author' => 'Compliance Officer',
                'publishDate' => '2024-09-10',
                'expiryDate' => '2024-10-10',
                'isActive' => true,
                'departments' => ['all'],
                'attachments' => []
            ],
            [
                'id' => 2,
                'title' => 'Actualización de horarios Customer Service',
                'content' => 'A partir del 20 de septiembre, el equipo de Customer Service tendrá cobertura extendida de 6:00 AM a 10:00 PM para mejor atención a clientes en diferentes zonas horarias.',
                'priority' => 'medium',
                'category' => 'operational',
                'author' => 'HR Coordinator',
                'publishDate' => '2024-09-12',
                'expiryDate' => '2024-09-25',
                'isActive' => true,
                'departments' => ['Comercial/Customer Service'],
                'attachments' => []
            ],
            [
                'id' => 3,
                'title' => 'Capacitación Dangerous Goods - Octubre',
                'content' => 'Se llevará a cabo capacitación IATA Dangerous Goods del 15-17 de octubre. Obligatorio para coordinadores aéreos. Inscripciones con RH antes del 30 de septiembre.',
                'priority' => 'high',
                'category' => 'training',
                'author' => 'Training Manager',
                'publishDate' => '2024-09-08',
                'expiryDate' => '2024-09-30',
                'isActive' => true,
                'departments' => ['Operaciones Aéreas'],
                'attachments' => [
                    ['name' => 'Programa_DG_Training.pdf', 'url' => '/files/dg-training.pdf']
                ]
            ],
            [
                'id' => 4,
                'title' => 'Nueva integración CargoWise One',
                'content' => 'Estamos implementando la nueva versión de CargoWise One. Habrá sesiones de entrenamiento la próxima semana para todos los usuarios.',
                'priority' => 'high',
                'category' => 'technology',
                'author' => 'IT Manager',
                'publishDate' => '2024-09-14',
                'expiryDate' => '2024-09-28',
                'isActive' => true,
                'departments' => ['all'],
                'attachments' => [
                    ['name' => 'CargoWise_Guide.pdf', 'url' => '/files/cargowise-guide.pdf']
                ]
            ],
            [
                'id' => 5,
                'title' => 'Junta mensual - Resultados Q3',
                'content' => 'Junta general el viernes 22 de septiembre a las 4:00 PM en sala de juntas. Se presentarán resultados del Q3 y objetivos para Q4.',
                'priority' => 'medium',
                'category' => 'meeting',
                'author' => 'General Manager',
                'publishDate' => '2024-09-15',
                'expiryDate' => '2024-09-22',
                'isActive' => true,
                'departments' => ['all'],
                'attachments' => []
            ],
            [
                'id' => 6,
                'title' => 'Nuevo cliente: BMW Mexico',
                'content' => 'Celebramos la incorporación de BMW Mexico como nuevo cliente. El equipo de Automotive se hará cargo de sus operaciones.',
                'priority' => 'medium',
                'category' => 'business',
                'author' => 'Sales Manager',
                'publishDate' => '2024-09-11',
                'expiryDate' => '2024-10-11',
                'isActive' => true,
                'departments' => ['Comercial/Customer Service', 'Operaciones Marítimas'],
                'attachments' => []
            ]
        ];

        $today = new \DateTime();
        $cutoffDate = (clone $today)->sub(new \DateInterval("P{$days}D"));

        $filtered = array_filter($baseAnnouncements, function($announcement) use ($cutoffDate, $priority, $activeOnly) {
            $publishDate = new \DateTime($announcement['publishDate']);
            $expiryDate = new \DateTime($announcement['expiryDate']);
            $now = new \DateTime();

            // Filtrar por fecha
            if ($publishDate < $cutoffDate) {
                return false;
            }

            // Filtrar por activo
            if ($activeOnly && (!$announcement['isActive'] || $expiryDate < $now)) {
                return false;
            }

            // Filtrar por prioridad
            if ($priority && $announcement['priority'] !== $priority) {
                return false;
            }

            return true;
        });

        // Agregar metadata y formatear
        return array_map(function($announcement) {
            $publishDate = new \DateTime($announcement['publishDate']);
            $expiryDate = new \DateTime($announcement['expiryDate']);
            $now = new \DateTime();
            
            return [
                'id' => $announcement['id'],
                'title' => $announcement['title'],
                'content' => $announcement['content'],
                'contentPreview' => $this->truncateText($announcement['content'], 150),
                'priority' => $announcement['priority'],
                'priorityLabel' => $this->getPriorityLabel($announcement['priority']),
                'category' => $announcement['category'],
                'categoryLabel' => $this->getCategoryLabel($announcement['category']),
                'author' => $announcement['author'],
                'publishDate' => $announcement['publishDate'],
                'publishDateFormatted' => $publishDate->format('d/m/Y'),
                'expiryDate' => $announcement['expiryDate'],
                'expiryDateFormatted' => $expiryDate->format('d/m/Y'),
                'daysUntilExpiry' => $now->diff($expiryDate)->days,
                'isActive' => $announcement['isActive'] && $expiryDate >= $now,
                'isNew' => $now->diff($publishDate)->days <= 3,
                'isExpiringSoon' => $now->diff($expiryDate)->days <= 7,
                'departments' => $announcement['departments'],
                'attachments' => $announcement['attachments'],
                'readMoreUrl' => "/announcements/{$announcement['id']}"
            ];
        }, array_values($filtered));
    }

    private function truncateText(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
    }

    private function getPriorityLabel(string $priority): string
    {
        $labels = [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'urgent' => 'Urgente'
        ];
        return $labels[$priority] ?? 'Media';
    }

    private function getCategoryLabel(string $category): string
    {
        $labels = [
            'general' => 'General',
            'operational' => 'Operacional',
            'compliance' => 'Cumplimiento',
            'training' => 'Capacitación',
            'technology' => 'Tecnología',
            'business' => 'Negocio',
            'meeting' => 'Junta',
            'hr' => 'Recursos Humanos'
        ];
        return $labels[$category] ?? 'General';
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
                new Rule(Rules::LESS_THAN, [91])
            ),
            new ParamRule(
                self::PARAMETER_PRIORITY,
                new Rule(Rules::IN, [['low', 'medium', 'high', 'urgent']])
            ),
            new ParamRule(
                self::PARAMETER_ACTIVE_ONLY,
                new Rule(Rules::BOOL_VAL)
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function getAll(): EndpointCollectionResult
    {
        return $this->getActiveAnnouncements();
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForGetOne(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule(CommonParams::PARAMETER_ID, new Rule(Rules::POSITIVE))
        );
    }

    /**
     * @inheritDoc
     */
    public function getOne(): EndpointResourceResult
    {
        $id = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_ATTRIBUTE, CommonParams::PARAMETER_ID);
        
        // Obtener anuncio específico (simulado)
        $announcements = $this->getSimulatedAnnouncements(90, null, false);
        $announcement = array_filter($announcements, fn($a) => $a['id'] === $id);
        
        if (empty($announcement)) {
            throw $this->getRecordNotFoundException();
        }
        
        return new EndpointResourceResult(ArrayModel::class, array_values($announcement)[0]);
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