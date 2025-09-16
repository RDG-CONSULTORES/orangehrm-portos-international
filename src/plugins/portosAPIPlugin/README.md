# Portos International API Plugin

Plugin personalizado para APIs de dashboard corporativo de Portos International.

## Endpoints Disponibles

### Dashboard APIs

#### Cumpleaños Próximos
```
GET /api/v2/portos/dashboard/cumpleanos?days=7&activeOnly=true
```

**Parámetros:**
- `days` (int, opcional): Días hacia adelante para buscar cumpleaños (default: 7, max: 30)
- `activeOnly` (bool, opcional): Solo empleados activos (default: true)

**Respuesta:**
```json
{
  "data": [
    {
      "empNumber": 1,
      "employeeId": "EMP001",
      "fullName": "Juan Carlos Mendoza",
      "jobTitle": "Gerente General",
      "department": "Administración",
      "birthday": "1978-09-20",
      "birthdayFormatted": "20/09",
      "daysUntilBirthday": 4,
      "ageWillBe": 46,
      "photo": "/pim/viewPhoto/empNumber/1",
      "isToday": false
    }
  ],
  "meta": {
    "total": 3,
    "days": 7,
    "todayCount": 1
  }
}
```

#### KPIs Básicos
```
GET /api/v2/portos/dashboard/kpis-basicos
```

**Respuesta:**
```json
{
  "data": {
    "summary": {
      "activeEmployees": 25,
      "availableToday": 23,
      "attendancePercentage": 92.0,
      "pendingLeaveApprovals": 3,
      "expiringCertifications": 5
    },
    "departmentAvailability": [
      {
        "department": "Operaciones Marítimas",
        "totalEmployees": 6,
        "availableToday": 6,
        "availabilityPercentage": 100.0
      }
    ],
    "metadata": {
      "date": "2024-09-16",
      "timezone": "America/Mexico_City"
    }
  }
}
```

### Anuncios APIs

#### Anuncios Activos
```
GET /api/v2/portos/dashboard/anuncios?days=30&priority=high&activeOnly=true
```

**Parámetros:**
- `days` (int, opcional): Días hacia atrás para buscar anuncios (default: 30, max: 90)
- `priority` (string, opcional): Filtrar por prioridad (low, medium, high, urgent)
- `activeOnly` (bool, opcional): Solo anuncios activos (default: true)

**Respuesta:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Nueva certificación CTPAT renovada",
      "content": "Portos International ha renovado...",
      "contentPreview": "Portos International ha renovado exitosamente...",
      "priority": "high",
      "priorityLabel": "Alta",
      "category": "compliance",
      "categoryLabel": "Cumplimiento",
      "author": "Compliance Officer",
      "publishDate": "2024-09-10",
      "expiryDate": "2024-10-10",
      "daysUntilExpiry": 24,
      "isActive": true,
      "isNew": false,
      "isExpiringSoon": false,
      "departments": ["all"],
      "attachments": []
    }
  ]
}
```

### Compliance APIs

#### Certificaciones por Vencer
```
GET /api/v2/portos/compliance/certificaciones?daysWarning=30&certificationType=iata&status=warning
```

**Parámetros:**
- `daysWarning` (int, opcional): Días de anticipación para alertas (default: 30, max: 90)
- `certificationType` (string, opcional): Tipo específico (iata, customs, dangerous_goods, ctpat)
- `department` (string, opcional): Filtrar por departamento
- `status` (string, opcional): Estado (all, valid, warning, critical, expired) (default: warning)

**Respuesta:**
```json
{
  "data": [
    {
      "empNumber": 5,
      "employeeName": "Ana Cristina Torres",
      "department": "Operaciones Marítimas",
      "jobTitle": "Senior Ocean Coordinator",
      "certificationType": "iata",
      "certificationName": "IATA",
      "expiryDate": "2024-10-15",
      "daysUntilExpiry": 29,
      "status": "warning",
      "statusLabel": "Por Vencer",
      "priority": 3,
      "isExpired": false
    }
  ],
  "meta": {
    "total": 5,
    "expired": 1,
    "critical": 2,
    "warning": 2
  }
}
```

#### Resumen Compliance Departamental
```
GET /api/v2/portos/compliance/departmental-summary
```

**Respuesta:**
```json
{
  "data": {
    "departments": [
      {
        "department": "Operaciones Marítimas",
        "totalEmployees": 6,
        "certifications": {
          "iata": {
            "certified": 4,
            "percentage": 66.7
          },
          "customs": {
            "certified": 1,
            "percentage": 16.7
          }
        }
      }
    ],
    "totals": {
      "totalEmployees": 25,
      "iataCertified": 8,
      "customsLicensed": 3
    }
  }
}
```

## Autenticación

Las APIs utilizan el sistema de autenticación de OrangeHRM. Se requiere:

1. **Session-based**: Cookie de sesión válida
2. **API Token**: Header `Authorization: Bearer {token}` (futuro)

## CORS

CORS está habilitado para las siguientes URLs:
- Dashboard corporativo interno
- Aplicaciones móviles autorizadas

## Rate Limiting

- **Límite**: 100 requests por minuto por IP
- **Headers de respuesta**:
  - `X-RateLimit-Limit`: Límite por ventana
  - `X-RateLimit-Remaining`: Requests restantes
  - `X-RateLimit-Reset`: Timestamp de reset

## Códigos de Error

| Código | Descripción |
|--------|-------------|
| 200 | Éxito |
| 400 | Parámetros inválidos |
| 401 | No autenticado |
| 403 | Sin permisos |
| 404 | Recurso no encontrado |
| 429 | Rate limit excedido |
| 500 | Error interno |

## Ejemplos de Uso

### JavaScript/Fetch
```javascript
// Obtener cumpleaños próximos
const response = await fetch('/api/v2/portos/dashboard/cumpleanos?days=7');
const birthdays = await response.json();

// Obtener KPIs básicos
const kpis = await fetch('/api/v2/portos/dashboard/kpis-basicos');
const data = await kpis.json();
```

### cURL
```bash
# Cumpleaños próximos
curl -X GET "https://portos-international-rh.onrender.com/api/v2/portos/dashboard/cumpleanos?days=7" \
  -H "Accept: application/json"

# Certificaciones por vencer
curl -X GET "https://portos-international-rh.onrender.com/api/v2/portos/compliance/certificaciones?daysWarning=30" \
  -H "Accept: application/json"
```

## Configuración

Las APIs se configuran mediante variables de entorno:

```env
PORTOS_API_ENABLED=true
PORTOS_DASHBOARD_ENABLED=true
PORTOS_CORS_ALLOWED_ORIGINS=*
PORTOS_RATE_LIMIT=100
```

## Changelog

### v2.0 (2024-09-16)
- APIs iniciales de dashboard
- Compliance y certificaciones
- Anuncios corporativos
- Documentación completa