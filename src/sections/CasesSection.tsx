import { useRef, useEffect, useState } from 'react'

interface CaseData {
  title: string
  subtitle: string
  stats: { label: string; value: string }[]
  description: string
  technologies: string
}

const cases: CaseData[] = [
  {
    title: 'ТЕХНОДАРК',
    subtitle: 'Инженерные системы и безопасность',
    stats: [
      { label: 'Органический трафик', value: '+210%' },
      { label: 'Контент за 4 мес', value: '32 статьи' },
      { label: 'Скорость производства', value: 'x3' }
    ],
    description:
      'Выстроил инфраструктуру для веб-платформы, создал модель привлечения клиентов, оптимизировал процессы контент-производства и внедрил инструменты аналитики для управления воронкой. AI-first подход в контент-процессы.',
    technologies: 'WordPress, PHP, OpenAI API, SEO-аналитика'
  },
  {
    title: 'ФРЕГАТ',
    subtitle: 'LMS-платформа и образовательный продукт',
    stats: [
      { label: 'Запуск', value: 'С нуля до MVP' },
      { label: 'ИИ в проектировании', value: '100%' },
      { label: 'Масштабируемость', value: 'Готово' }
    ],
    description:
      'Разработал и развернул инфраструктуру для сайта и LMS-платформы, настроил систему маркетинга и аналитики, обеспечил техническую основу для масштабирования образовательного продукта.',
    technologies: 'LMS, AI-проектирование, маркетинг, аналитика'
  },
  {
    title: 'ГАСПАР АГРО',
    subtitle: 'Агротехника и запчасти на Avito',
    stats: [
      { label: 'Время анализа', value: '5-8ч → 30 мин' },
      { label: 'Конверсия', value: '0.5% → 1.2%' },
      { label: 'CPL', value: '−35%' }
    ],
    description:
      'ИИ-ассистент маркетолога: автоматический сбор статистики Avito, генерация 5 вариантов заголовков, дашборд с CPL и ROI. Время анализа сокращено с 5-8 часов до 30 минут в день.',
    technologies: 'PHP 8.4, JavaScript, Chart.js, MySQL, Avito API, z.ai'
  },
  {
    title: 'СТИЛСОФТ',
    subtitle: 'Промышленный холдинг, 1800+ сотрудников',
    stats: [
      { label: 'Экономия бюджета', value: '10+ млн ₽' },
      { label: 'CRM + 1С:ERP', value: 'Внедрено' },
      { label: 'Время отчётности', value: 'Недели → часы' }
    ],
    description:
      'Руководитель проектов: внедрил CRM (Bitrix24) с интеграцией в 1С:ERP и 1С:Документооборот, WMS-систему, управленческий учёт (1С:БитФинанс) с экономией 10+ млн ₽, систему раздельного учёта для ГОЗ.',
    technologies: 'Bitrix24, 1С:ERP, 1С:БитФинанс, WMS'
  }
]

function RollingCounter({ value, delay }: { value: string; delay: number }) {
  const [displayed, setDisplayed] = useState('0')
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setTimeout(() => {
              const numericMatch = value.match(/[\d.]+/)
              if (!numericMatch) {
                setDisplayed(value)
                return
              }

              const targetNum = parseFloat(numericMatch[0])
              const prefix = value.substring(0, value.indexOf(numericMatch[0]))
              const suffix = value.substring(
                value.indexOf(numericMatch[0]) + numericMatch[0].length
              )

              const duration = 1500
              const startTime = performance.now()

              const animate = (currentTime: number) => {
                const elapsed = currentTime - startTime
                const progress = Math.min(elapsed / duration, 1)
                const eased = 1 - Math.pow(1 - progress, 3)
                const current = targetNum * eased

                if (Number.isInteger(targetNum)) {
                  setDisplayed(`${prefix}${Math.round(current)}${suffix}`)
                } else {
                  setDisplayed(`${prefix}${current.toFixed(1)}${suffix}`)
                }

                if (progress < 1) {
                  requestAnimationFrame(animate)
                } else {
                  setDisplayed(value)
                }
              }

              requestAnimationFrame(animate)
            }, delay)

            observer.unobserve(entry.target)
          }
        })
      },
      { threshold: 0.5 }
    )

    if (ref.current) {
      observer.observe(ref.current)
    }

    return () => observer.disconnect()
  }, [value, delay])

  return (
    <div ref={ref} style={{ fontFamily: "'Roboto Mono', monospace", fontSize: '14px' }}>
      {displayed}
    </div>
  )
}

export default function CasesSection() {
  const sectionRef = useRef<HTMLElement>(null)
  const contentRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            if (contentRef.current) {
              contentRef.current.style.opacity = '1'
            }
            observer.unobserve(entry.target)
          }
        })
      },
      { threshold: 0.15 }
    )

    if (sectionRef.current) {
      observer.observe(sectionRef.current)
    }

    return () => observer.disconnect()
  }, [])

  return (
    <section
      id="cases"
      ref={sectionRef}
      className="section-dark relative overflow-hidden"
    >
      <div
        ref={contentRef}
        className="grid-container"
        style={{
          opacity: 0,
          transition: 'opacity 1s ease'
        }}
      >
        {/* Section Header */}
        <div className="col-span-12 mb-16">
          <h2
            className="font-mono-accent text-white"
            style={{ fontSize: '12px' }}
          >
            (03) КЕЙСЫ
          </h2>
        </div>

        {/* Cases - 2x2 Grid */}
        {cases.map((caseItem, caseIndex) => (
          <div
            key={caseItem.title}
            className="col-span-6 px-6 mb-12"
            style={{
              borderLeft: caseIndex % 2 === 1 ? '1px solid #2A2A2A' : 'none'
            }}
          >
            {/* Title */}
            <h3
              className="text-white font-medium mb-2"
              style={{
                fontSize: 'clamp(24px, 3vw, 36px)',
                lineHeight: '1',
                letterSpacing: '-0.02em'
              }}
            >
              {caseItem.title}
            </h3>
            <p
              className="text-secondary-dark mb-8"
              style={{ fontSize: '13px' }}
            >
              {caseItem.subtitle}
            </p>

            {/* Stats */}
            <div className="space-y-4 mb-8">
              {caseItem.stats.map((stat, statIndex) => (
                <div key={stat.label}>
                  <div
                    className="text-secondary-dark mb-1"
                    style={{ fontSize: '11px' }}
                  >
                    {stat.label}
                  </div>
                  <div className="text-white">
                    <RollingCounter
                      value={stat.value}
                      delay={caseIndex * 300 + statIndex * 150}
                    />
                  </div>
                </div>
              ))}
            </div>

            {/* Description */}
            <p
              className="text-secondary-dark mb-6"
              style={{ fontSize: '13px', lineHeight: '20px' }}
            >
              {caseItem.description}
            </p>

            {/* Technologies */}
            <p
              className="font-mono-accent text-secondary-dark mb-8"
              style={{ fontSize: '10px' }}
            >
              {caseItem.technologies}
            </p>

            {/* CTA */}
            <a
              href="https://t.me/moi_imperator"
              target="_blank"
              rel="noopener noreferrer"
              className="font-mono-accent text-white border border-white px-5 py-2 hover:bg-white hover:text-black transition-colors inline-block"
              style={{ fontSize: '11px' }}
            >
              УЗНАТЬ ПОДРОБНЕЕ
            </a>
          </div>
        ))}
      </div>
    </section>
  )
}
