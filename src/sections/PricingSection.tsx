import { useRef, useEffect } from 'react'

interface PricingPlan {
  price: string
  title: string
  target: string
  features: string[]
  cta: string
  highlighted?: boolean
}

const plans: PricingPlan[] = [
  {
    price: 'Бесплатно',
    title: '30-МИНУТНЫЙ ЗВОНОК',
    target: 'Понять, подходят ли мои услуги',
    features: [
      '30-минутный созвон',
      'Разбор вашей задачи',
      'Предварительная оценка ROI',
      'Рекомендации по следующим шагам'
    ],
    cta: 'ЗАПИСАТЬСЯ НА ЗВОНОК'
  },
  {
    price: 'от 80 000 ₽/мес',
    title: 'IT-БИЗНЕС-ПАРТНЁР',
    target: 'Постоянное сопровождение',
    features: [
      'Аудит и стратегия цифровизации',
      'Управление портфелем проектов',
      'AI-first подход ко всем процессам',
      'Вайб-кодинг для быстрых решений',
      'Еженедельные синхронизации',
      'Приоритетная поддержка'
    ],
    cta: 'ОБСУДИТЬ ПАРТНЁРСТВО',
    highlighted: true
  },
  {
    price: 'от 150 000 ₽',
    title: 'ПРОЕКТ ПОД КЛЮЧ',
    target: 'Конкретный проект с чётким ТЗ',
    features: [
      'Полный цикл: от аудита до запуска',
      'Разработка и внедрение решения',
      'Интеграция с существующими системами',
      'Обучение команды',
      '1 месяц поддержки после запуска',
      'Гарантия результата'
    ],
    cta: 'ОБСУДИТЬ ПРОЕКТ'
  }
]

export default function PricingSection() {
  const sectionRef = useRef<HTMLElement>(null)
  const cardsRef = useRef<(HTMLDivElement | null)[]>([])

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            cardsRef.current.forEach((card, i) => {
              if (card) {
                setTimeout(() => {
                  card.style.opacity = '1'
                  card.style.transform = 'translateY(0)'
                }, i * 200)
              }
            })
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
      id="pricing"
      ref={sectionRef}
      className="section-dark relative overflow-hidden"
    >
      <div className="grid-container">
        {/* Section Header */}
        <div className="col-span-12 mb-16">
          <h2
            className="font-mono-accent text-white"
            style={{ fontSize: '12px' }}
          >
            (05) ТАРИФЫ
          </h2>
        </div>

        {/* Pricing Cards */}
        {plans.map((plan, index) => (
          <div
            key={plan.title}
            ref={(el) => { cardsRef.current[index] = el }}
            className="col-span-4"
            style={{
              opacity: 0,
              transform: 'translateY(30px)',
              transition: 'all 0.8s cubic-bezier(0.16, 1, 0.3, 1)'
            }}
          >
            <div
              className={`p-8 h-full flex flex-col transition-all duration-300 hover:-translate-y-2 ${
                plan.highlighted
                  ? 'border border-[#2C2CFF]'
                  : 'border border-[#2A2A2A]'
              }`}
            >
              {/* Price */}
              <div
                className="text-white font-medium mb-2"
                style={{
                  fontSize: 'clamp(32px, 3vw, 48px)',
                  lineHeight: '1',
                  letterSpacing: '-0.02em'
                }}
              >
                {plan.price}
              </div>

              {/* Title */}
              <h3
                className="text-white font-medium mb-2"
                style={{
                  fontSize: '18px',
                  lineHeight: '24px'
                }}
              >
                {plan.title}
              </h3>

              {/* Target */}
              <p
                className="text-secondary-dark mb-6"
                style={{ fontSize: '13px' }}
              >
                {plan.target}
              </p>

              {/* Features */}
              <ul className="space-y-3 mb-6 flex-grow">
                {plan.features.map((feature) => (
                  <li
                    key={feature}
                    className="text-secondary-dark flex items-start gap-2"
                    style={{ fontSize: '14px', lineHeight: '20px' }}
                  >
                    <span className="text-[#2C2CFF] mt-1">+</span>
                    {feature}
                  </li>
                ))}
              </ul>

              {/* CTA */}
              <a
                href="https://t.me/moi_imperator"
                target="_blank"
                rel="noopener noreferrer"
                className={`font-mono-accent text-center py-3 transition-colors ${
                  plan.highlighted
                    ? 'bg-[#2C2CFF] text-white hover:bg-[#1a1acc]'
                    : 'border border-white text-white hover:bg-white hover:text-black'
                }`}
                style={{ fontSize: '12px' }}
              >
                {plan.cta}
              </a>
            </div>
          </div>
        ))}
      </div>
    </section>
  )
}
