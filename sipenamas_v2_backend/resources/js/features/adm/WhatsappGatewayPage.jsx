import React, { useState, useEffect } from 'react'
import {
  MessageSquareShare,
  Smartphone,
  Wifi,
  Send,
  CheckCircle2,
  AlertCircle,
  RefreshCw,
} from 'lucide-react'
import { adminApi } from '../../services/api/adminApi'
import { Card, CardHeader, CardContent } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'

export default function WhatsappGatewayPage() {
  const [gateway, setGateway] = useState(null)
  const [targetGroup, setTargetGroup] = useState('reviewer_pending')
  const [messageTemplate, setMessageTemplate] = useState(
    'Yth. Bapak/Ibu Reviewer LPPM UKWMS, mohon segera menyelesaikan evaluasi rubrik proposal penelitian TA 2026 Gelombang I sebelum batas akhir 15 April 2026 melalui portal: https://lppm.ukwms.ac.id. Terima kasih.'
  )
  const [isSending, setIsSending] = useState(false)
  const [statusNotice, setStatusNotice] = useState('')

  useEffect(() => {
    async function load() {
      const res = await adminApi.getWhatsappGatewayStatus()
      setGateway(res.data)
    }
    load()
  }, [])

  const handleSend = async (e) => {
    e.preventDefault()
    setIsSending(true)
    try {
      const res = await adminApi.sendWhatsappBroadcast({
        recipientGroup: targetGroup,
        messageText: messageTemplate,
      })
      setStatusNotice(res.message)
      setTimeout(() => setStatusNotice(''), 5000)
    } finally {
      setIsSending(false)
    }
  }

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            WhatsApp Broadcast & Gateway Notifikasi
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Pengiriman pesan otomatis pengingat deadline revisi, undangan reviewer, dan pengumuman lolos pendanaan
          </p>
        </div>
      </div>

      {statusNotice && (
        <div className="p-3.5 bg-[#10c469]/10 border border-[#10c469]/30 text-[#0b7941] rounded-xl text-xs flex items-center gap-2.5 shadow-sm">
          <CheckCircle2 className="w-4 h-4 text-[#10c469] shrink-0" />
          <span>{statusNotice}</span>
        </div>
      )}

      {/* Gateway Hardware Status & Form */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Device Status Card */}
        <div className="lg:col-span-4 space-y-4">
          <Card className="border-[#e7e9eb] shadow-sm">
            <CardHeader
              title={<span className="font-heading font-bold text-[#313a46]">Status Perangkat Gateway</span>}
              subtitle={<span className="text-xs text-[#98a6ad]">Koneksi server API WhatsApp</span>}
            />
            <CardContent className="space-y-4 text-xs">
              <div className="p-3 rounded-xl bg-[#10c469]/10 border border-[#10c469]/30 text-[#0b7941] flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Wifi className="w-4 h-4 text-[#10c469]" />
                  <span className="font-semibold">Server Terhubung</span>
                </div>
                <Badge variant="soft-success" pill>Online</Badge>
              </div>

              <div className="space-y-2.5 border-t border-[#e7e9eb] pt-3 text-[#6c757d]">
                <div className="flex justify-between items-center py-1 border-b border-[#e7e9eb]/60">
                  <span className="text-[#98a6ad]">Nomor Gateway:</span>
                  <strong className="font-mono text-[#313a46]">{gateway?.deviceNumber || '+62 812-3456-7890'}</strong>
                </div>
                <div className="flex justify-between items-center py-1 border-b border-[#e7e9eb]/60">
                  <span className="text-[#98a6ad]">Nama Perangkat:</span>
                  <strong className="text-[#313a46]">{gateway?.deviceName}</strong>
                </div>
                <div className="flex justify-between items-center py-1 border-b border-[#e7e9eb]/60">
                  <span className="text-[#98a6ad]">Baterai Server:</span>
                  <strong className="text-[#10c469] font-bold">{gateway?.batteryLevel}%</strong>
                </div>
                <div className="flex justify-between items-center py-1">
                  <span className="text-[#98a6ad]">Siaran Terakhir:</span>
                  <span className="text-[#313a46] font-medium">{gateway?.lastBroadcast}</span>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Broadcast Sender Form */}
        <div className="lg:col-span-8">
          <Card className="border-[#e7e9eb] shadow-sm">
            <CardHeader
              title={<span className="font-heading font-bold text-[#313a46]">Kirim Siaran Notifikasi Massal</span>}
              subtitle={<span className="text-xs text-[#98a6ad]">Pesan akan dikirim secara berurutan ke nomor kontak WhatsApp terdaftar</span>}
            />
            <CardContent>
              <form onSubmit={handleSend} className="space-y-4 text-xs">
                <div>
                  <label className="block font-semibold text-[#313a46] mb-1.5">
                    Target Grup Penerima
                  </label>
                  <select
                    value={targetGroup}
                    onChange={(e) => setTargetGroup(e.target.value)}
                    className="w-full p-2.5 bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg text-xs text-[#313a46] focus:outline-none focus:border-[#188ae2] focus:bg-white transition-colors"
                  >
                    <option value="reviewer_pending">
                      Reviewer yang Belum Menyelesaikan Penilaian (Prioritas)
                    </option>
                    <option value="dosen_revisi">
                      Dosen Peneliti dengan Status Perlu Revisi Proposal
                    </option>
                    <option value="dekan_pending">
                      Dekan Fakultas yang Memiliki Usulan Menunggu Approval
                    </option>
                    <option value="all_dosen">
                      Semua Dosen Ber-NIDN UKWMS (Pengumuman Pembukaan Hibah)
                    </option>
                  </select>
                </div>

                <div>
                  <label className="block font-semibold text-[#313a46] mb-1.5">
                    Format Naskah Pesan WhatsApp
                  </label>
                  <textarea
                    rows={6}
                    required
                    value={messageTemplate}
                    onChange={(e) => setMessageTemplate(e.target.value)}
                    className="w-full p-3 bg-[#f6f7fb] border border-[#e7e9eb] rounded-xl text-xs text-[#313a46] focus:outline-none focus:border-[#188ae2] focus:bg-white transition-colors font-sans leading-relaxed"
                  />
                  <span className="text-[11px] text-[#98a6ad] mt-1.5 block">
                    Panjang pesan: <strong className="text-[#313a46]">{messageTemplate.length}</strong> karakter
                  </span>
                </div>

                <div className="pt-2 flex justify-end">
                  <Button
                    type="submit"
                    variant="primary"
                    size="sm"
                    iconLeft={Send}
                    isLoading={isSending}
                  >
                    Kirim Siaran Pesan WhatsApp
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}
