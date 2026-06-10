import { useState } from 'react'
import DataCloudCanvas from '../components/DataCloud'
import Typewriter from '../components/Typewriter'

export default function HeroSection() {
  const [subtitleStarted, setSubtitleStarted] = useState(false)

  return (
    <section
      id="hero"
      className="relative w-full overflow-hidden"
      style={{ height: '100vh', backgroundColor: '#141414' }}
    >
      {/* 3D Data Cloud Background */}
      <DataCloudCanvas />

      {/* Background Video */}
      <video
        autoPlay
        loop
        muted
        playsInline
        className="absolute inset-0 w-full h-full object-cover"
        style={{ opacity: 0.3, mixBlendMode: 'screen', zIndex: 1 }}
      >
        <source src="/videos/video_hero_network.mp4" type="video/mp4" />
      </video>

      {/* Content */}
      <div
        className="relative grid-container h-full flex flex-col justify-center"
        style={{ zIndex: 10 }}
      >
        <div className="col-span-8">
          <h1
            className="text-white font-medium"
            style={{
              fontSize: 'clamp(36px, 7vw, 84px)',
              lineHeight: '1.05',
              letterSpacing: '-0.04em',
              fontFamily: "'Inter', sans-serif"
            }}
          >
            <Typewriter
              text="НЕЙРОСЕТЕВЫЕ ТЕХНОЛОГИИ<br>ДЛЯ БИЗНЕСА"
              speed={80}
              onComplete={() => setSubtitleStarted(true)}
            />
          </h1>

          <div className="mt-8" style={{ minHeight: '60px' }}>
            {subtitleStarted && (
              <p
                className="text-secondary-dark"
                style={{
                  fontFamily: "'Roboto Mono', monospace",
                  fontSize: '14px',
                  lineHeight: '21px'
                }}
              >
                <Typewriter
                  text="IT-бизнес-партнёр с 16-летним опытом. Внедряю ИИ в процессы: от контент-маркетинга до LMS-платформ. Два реальных проекта — Технодарк и Фрегат."
                  speed={50}
                  delay={200}
                />
              </p>
            )}
          </div>
        </div>

        {/* CTA Button */}
        <div className="absolute bottom-12 right-12">
          <a
            href="https://t.me/moi_imperator"
            target="_blank"
            rel="noopener noreferrer"
            className="font-mono-accent text-white border-b border-white pb-1 hover:text-[#2C2CFF] hover:border-[#2C2CFF] transition-colors"
          >
            НАПИСАТЬ В ТЕЛЕГРАМ
          </a>
        </div>
      </div>
    </section>
  )
}
